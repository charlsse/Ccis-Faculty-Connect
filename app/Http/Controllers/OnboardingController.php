<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Services\PrivacyDocumentation;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function password(Request $request)
    {
        if (! $request->user()->must_change_password) {
            abort_if($request->user()->onboardingRoute() === 'onboarding.password', 403, 'Request an invitation from your administrator.');

            return redirect()->route($request->user()->onboardingRoute() ?? 'two-factor.challenge');
        }

        return view('auth.change-temporary-password');
    }

    public function replacePassword(Request $request, SessionManager $sessions, AuditLogger $audit, PrivacyDocumentation $documents)
    {
        $policies = $documents->acknowledgmentPolicies();
        $rules = ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]];
        if ($policies) {
            foreach ($policies as $kind => $policy) {
                $rules[$kind.'_version'] = ['required', Rule::in([$policy['version']])];
                $rules[$kind.'_sha256'] = ['required', Rule::in([$policy['sha256']])];
            }
            $rules['privacy_acknowledged'] = ['required', 'accepted'];
            $rules['terms_accepted'] = ['required', 'accepted'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $data, $sessions, $audit, $policies): void {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->must_change_password && $user->temporary_password_expires_at?->isFuture()
                && (int) $request->session()->get('security_version') === $user->security_version, 403);
            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a password different from the temporary password.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false,
                'temporary_password_expires_at' => null, 'password_replaced_at' => now(), 'remember_token' => null])->save();
            $sessions->invalidate($user, $request->session()->getId());
            $request->session()->regenerate();
            $request->session()->put('security_version', $user->security_version);
            $request->setUserResolver(fn () => $user);
            Auth::setUser($user);
            $audit->record('onboarding.password_replaced', $user, $user);
            if ($policies) {
                $audit->record('policy.acknowledged', $user, $user, [
                    'privacy_version' => $policies['privacy']['version'], 'privacy_sha256' => $policies['privacy']['sha256'],
                    'terms_version' => $policies['terms']['version'], 'terms_sha256' => $policies['terms']['sha256'],
                    'privacy_notice_received' => true, 'terms_accepted' => true, 'purpose_specific_consent' => false,
                ]);
            }
        });

        return redirect()->route($request->user()->onboardingRoute() ?? 'two-factor.challenge');
    }

    public function notice(Request $request)
    {
        return $request->user()->email_verified_at
            ? redirect()->route($request->user()->onboardingRoute() ?? 'two-factor.challenge')
            : view('auth.verify-email');
    }

    public function send(Request $request, AuditLogger $audit)
    {
        abort_if($request->user()->isAcademicRosterDemo(), 422, 'Academic demonstration accounts use authenticator-app enrollment.');
        if (! $request->user()->email_verified_at) {
            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                $audit->record('email_verification.delivery_failed', $request->user(), details: ['exception_type' => $exception::class]);

                return back()->withErrors(['email' => 'The message could not be sent. Please retry.']);
            }
        }

        return back()->with('status', 'Verification message accepted by the mail transport. Check your inbox.');
    }

    public function verify(Request $request, string $id, string $hash, AuditLogger $audit)
    {
        $user = $request->user();
        abort_if($user->email === null || $user->isAcademicRosterDemo(), 422, 'No email address is available to verify.');
        abort_unless((string) $user->id === $id && hash_equals(sha1($user->email), $hash), 403);
        $user->markEmailAsVerified();
        $audit->record('account.email_verified', $user, $user);

        return redirect()->route($user->onboardingRoute() ?? 'two-factor.challenge');
    }
}
