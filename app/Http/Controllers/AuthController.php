<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionManager;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TwoFactorService $twoFactor,
        private readonly SessionManager $sessions,
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:180'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $key = 'login:'.hash('sha256', Str::lower($credentials['login']).'|'.$request->ip());
        $this->ensureNotRateLimited($key, 'login');

        $user = User::query()
            ->where(function ($query) use ($credentials): void {
                $query->whereRaw('LOWER(email) = ?', [Str::lower($credentials['login'])])
                    ->orWhere('faculty_id', $credentials['login']);
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('login.failed', $user, null, ['reason' => 'invalid_credentials']);
            if ($user && RateLimiter::attempts($key) === 5) {
                app(\App\Services\PortalNotifications::class)->security($user, 'Multiple unsuccessful sign-in attempts were detected for your account. If this was not you, review your account security.', true);
            }

            throw ValidationException::withMessages([
                'login' => 'The provided employee ID/email or password is incorrect.',
            ]);
        }

        if ($user->status === User::STATUS_DEACTIVATED) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('login.failed', $user, null, ['reason' => 'deactivated']);

            throw ValidationException::withMessages([
                'login' => 'This account is deactivated. Contact an administrator.',
            ]);
        }

        if (($user->must_change_password && ! $user->temporary_password_expires_at?->isFuture())
            || ($user->status === User::STATUS_PENDING && ! $user->must_change_password && ! $user->password_replaced_at)) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('login.failed', $user, null, ['reason' => 'pending_activation']);

            throw ValidationException::withMessages([
                'login' => 'Request a new invitation from your administrator; these temporary credentials are unavailable or expired.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if ($destination = $user->onboardingRoute()) {
            Auth::login($user, false);
            $request->session()->put('security_version', $user->security_version);

            $response = redirect()->route($destination);

            return $request->hasCookie(config('trusted-device.cookie'))
                ? $response->withCookie($this->trustedDevices->forgetCookie())
                : $response;
        }

        $trustedDevice = $this->trustedDevices->consume($user, $request);
        if ($trustedDevice->valid) {
            $this->completeLogin($request, $user, false, 'trusted_device');
            $response = redirect()->intended(route('dashboard'));

            return $trustedDevice->cookie ? $response->withCookie($trustedDevice->cookie) : $response;
        }

        $request->session()->put([
            'pending_login_user_id' => $user->id,
            'pending_login_version' => $user->security_version,
            'pending_login_expires' => now()->addMinutes(10)->timestamp,
            'pending_login_remember' => false,
            'pending_trusted_device_renewal_id' => $trustedDevice->device?->user_id === $user->id
                ? $trustedDevice->device->id
                : null,
        ]);

        $response = redirect()->route('two-factor.challenge');

        return $trustedDevice->forgetCookie
            ? $response->withCookie($this->trustedDevices->forgetCookie())
            : $response;
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $request->user() ?: User::find($request->session()->get('pending_login_user_id'));

        if (! $this->validChallengeSession($request, $user)) {
            return redirect()->route('login');
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor-challenge', ['user' => $user]);
    }

    public function challenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'trust_device' => ['nullable', 'boolean'],
        ]);
        $user = $request->user() ?: User::find($request->session()->get('pending_login_user_id'));

        if (! $this->validChallengeSession($request, $user)) {
            return redirect()->route('login');
        }

        $key = 'otp:'.$user->id.'|'.$request->ip();
        $this->ensureNotRateLimited($key, 'code');
        $code = (string) $request->input('code');
        $isTotp = (bool) preg_match('/^\d{6}$/', $code);
        $valid = $isTotp
            ? $this->twoFactor->verifyTotp($user, $code)
            : $this->twoFactor->useRecoveryCode($user, $code);

        if (! $valid) {
            RateLimiter::hit($key, 60);
            $this->auditLogger->record('two_factor.challenge_failed', $user);
            if (RateLimiter::attempts($key) === 5) {
                app(\App\Services\PortalNotifications::class)->security($user, 'Multiple unsuccessful two-factor verification attempts were detected for your account. Review your account security.', true);
            }

            throw ValidationException::withMessages(['code' => 'The authentication or recovery code is invalid.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('pending_login_remember', false);
        $request->session()->forget('pending_login_user_id');
        $renewedFromId = $request->session()->pull('pending_trusted_device_renewal_id');
        $this->completeLogin($request, $user, $remember, $isTotp ? 'totp' : 'recovery_code');
        $response = redirect()->intended(route('dashboard'));

        if ($isTotp && $request->boolean('trust_device')) {
            $trustedDevice = $this->trustedDevices->issue($user, $request, $renewedFromId ? (int) $renewedFromId : null);

            return $response->withCookie($trustedDevice->cookie);
        }

        return $response;
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->string('email'))
            ->where('must_change_password', false)
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhere(fn ($pending) => $pending->where('status', User::STATUS_PENDING)->whereNotNull('password_replaced_at'));
            })->first();

        if ($user) {
            try {
                PasswordBroker::sendResetLink(['email' => $user->email]);
                $this->auditLogger->record('password_reset.requested', $user);
            } catch (Throwable $exception) {
                $this->auditLogger->record('password_reset.delivery_failed', $user, details: ['exception_type' => $exception::class]);

                return back()->withErrors(['email' => 'The reset email could not be delivered. Please contact support.']);
            }
        }

        return back()->with('status', 'If an active account matches that address, a password-reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $status = PasswordBroker::reset($validated, function (User $user, string $password): void {
            abort_if($user->status === User::STATUS_DEACTIVATED || $user->must_change_password, 403);
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $this->sessions->invalidate($user);
            event(new PasswordReset($user));
            $this->auditLogger->record('password.reset', $user);
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return redirect()->route('login')
            ->with('status', 'Password reset complete. Sign in with your new password and authentication code.')
            ->withCookie($this->trustedDevices->forgetCookie());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    public function logoutAndForget(Request $request): RedirectResponse
    {
        $this->trustedDevices->revokeCurrent($request->user(), $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->withCookie($this->trustedDevices->forgetCookie());
    }

    private function validChallengeSession(Request $request, ?User $user): bool
    {
        if (! $user || $user->status === User::STATUS_DEACTIVATED || $user->onboardingRoute()) {
            return false;
        }

        return $request->user()
            ? (int) $request->session()->get('security_version', -1) === $user->security_version
            : ((int) $request->session()->get('pending_login_version', -1) === $user->security_version
                && (int) $request->session()->get('pending_login_expires', 0) > now()->timestamp);
    }

    private function ensureNotRateLimited(string $key, string $field): void
    {
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        throw ValidationException::withMessages([
            $field => "Too many attempts. Try again in {$seconds} seconds.",
        ]);
    }

    private function completeLogin(Request $request, User $user, bool $remember, string $method): void
    {
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put([
            'two_factor_user_id' => $user->id,
            'security_version' => $user->security_version,
        ]);
        if (! $user->onboarding_completed_at) {
            $this->auditLogger->record('onboarding.completed', $user, $user);
        }
        $user->forceFill(['last_login_at' => now(), 'status' => User::STATUS_ACTIVE,
            'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
            'activated_at' => $user->activated_at ?? now()])->save();
        $this->auditLogger->record('login.succeeded', $user, $user, ['method' => $method]);
    }
}
