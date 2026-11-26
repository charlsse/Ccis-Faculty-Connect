<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogger $auditLogger,
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('security.show');
        }

        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->twoFactor->generateSecret()])->save();
        }

        return view('auth.two-factor-setup', [
            'user' => $user,
            'qrCode' => $this->twoFactor->qrCodeDataUri($user),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6'], 'trust_device' => ['nullable', 'boolean']]);
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 403, 'Already enrolled. Use Security Settings.');
        $key = 'otp-setup:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! $this->twoFactor->verifyTotp($user, (string) $request->input('code'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        RateLimiter::clear($key);
        $codes = $this->twoFactor->generateRecoveryCodes($user);
        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'activated_at' => $user->activated_at ?? now(),
            'two_factor_confirmed_at' => now(),
            'last_login_at' => now(),
            'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
        ])->save();
        $request->session()->put([
            'two_factor_user_id' => $user->id,
            'security_version' => $user->security_version,
            'new_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
        ]);
        $this->auditLogger->record('two_factor.enabled', $user, $user);
        $this->auditLogger->record('account.activated', $user, $user);

        $this->auditLogger->record('onboarding.completed', $user, $user);
        $response = redirect()->route('two-factor.recovery-codes');

        return $request->boolean('trust_device')
            ? $response->withCookie($this->trustedDevices->issue($user, $request)->cookie)
            : $response;
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $encryptedCodes = $request->session()->pull('new_recovery_codes');

        if (! $encryptedCodes) {
            return redirect()->route('security.show');
        }

        $codes = json_decode(Crypt::decryptString($encryptedCodes), true, flags: JSON_THROW_ON_ERROR);

        return view('auth.recovery-codes', ['codes' => $codes]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $codes = $this->twoFactor->generateRecoveryCodes($request->user());
        $this->auditLogger->record('two_factor.recovery_codes_regenerated', $request->user(), $request->user());

        return redirect()->route('two-factor.recovery-codes')->with(
            'new_recovery_codes',
            Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
        );
    }
}
