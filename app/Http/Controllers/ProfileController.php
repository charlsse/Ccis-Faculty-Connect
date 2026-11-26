<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\VerifyNewEmail;
use App\Services\AuditLogger;
use App\Services\SessionManager;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionManager $sessionManager,
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    public function show(Request $request): View
    {
        return view('dashboard.profile.show', [
            'user' => $request->user(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = Str::lower((string) $request->input('email')) !== Str::lower($user->email);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [$user->isAcademicRosterDemo() ? 'nullable' : 'required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'department' => ['required', 'string', 'max:180'],
            'position' => ['required', 'string', 'max:180'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => [$emailChanged ? 'required' : 'nullable', $emailChanged ? 'current_password' : 'nullable'],
        ]);

        $profile = collect($validated)->only([
            'first_name', 'middle_name', 'last_name', 'department', 'position', 'contact_number', 'bio',
        ])->all();

        if ($request->hasFile('profile_photo')) {
            $newPath = $request->file('profile_photo')->store('profile-photos', 'public');
            if ($user->profile_photo && Str::startsWith($user->profile_photo, 'profile-photos/')) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $profile['profile_photo'] = $newPath;
        }

        $user->update($profile);

        if ($emailChanged) {
            $this->requestEmailChange($user, Str::lower($validated['email']));
        }

        $this->auditLogger->record('profile.updated', $user, $user, [
            'fields' => array_keys($profile),
            'email_change_requested' => $emailChanged,
        ]);

        $message = $emailChanged
            ? 'Profile updated. Verify the message sent to your new email address before it replaces your current address.'
            : 'Profile information has been updated.';

        return back()->with('status', $message);
    }

    public function verifyEmail(User $user, Request $request): RedirectResponse
    {
        $token = (string) $request->query('token');
        abort_unless(
            $user->pending_email
            && $user->pending_email_token_hash
            && $token !== ''
            && hash_equals($user->pending_email_token_hash, hash('sha256', $token))
            && $user->pending_email_expires_at?->isFuture(),
            403,
            'This email verification link is invalid, expired, or already used.',
        );

        $newEmail = $user->pending_email;
        abort_if(User::where('email', $newEmail)->whereKeyNot($user->id)->exists(), 409, 'That email address is already in use.');
        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();
        $this->sessionManager->invalidate($user);
        $this->auditLogger->record('profile.email_changed', $user, $user);

        return redirect()->route('login')->with('status', 'Your new email address is verified. Please sign in again.');
    }

    public function security(Request $request): View
    {
        $user = $request->user();
        $sessions = collect();

        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get();
        }

        return view('dashboard.profile.security', [
            'user' => $user,
            'sessions' => $sessions,
            'currentSessionId' => $request->session()->getId(),
            'trustedDevices' => $user->trustedDevices()->latest('first_trusted_at')->limit(25)->get(),
            'currentTrustedDeviceId' => $this->trustedDevices->current($user, $request)?->id,
            'activities' => AuditLog::where('subject_user_id', $user->id)->latest('created_at')->limit(12)->get(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($validated['password']), 'remember_token' => Str::random(60)])->save();
        $this->sessionManager->invalidate($user, $request->session()->getId());
        $request->session()->put('security_version', $user->security_version);
        $this->auditLogger->record('password.changed', $user, $user);

        return back()
            ->with('status', 'Password changed. Other sessions and every trusted device have been revoked.')
            ->withCookie($this->trustedDevices->forgetCookie());
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_if(hash_equals($request->session()->getId(), $session), 422, 'Use logout to end the current session.');
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $session)
            ->where('user_id', $request->user()->id)
            ->delete();
        abort_unless($deleted, 404);
        $this->auditLogger->record('session.revoked', $request->user(), $request->user(), ['session_reference' => substr($session, 0, 8)]);

        return back()->with('status', 'The selected session has been revoked.');
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $this->sessionManager->invalidate($request->user(), $request->session()->getId());
        $request->session()->put('security_version', $request->user()->security_version);
        $this->auditLogger->record('sessions.others_revoked', $request->user(), $request->user());

        return back()
            ->with('status', 'All other sessions and trusted-device authorizations have been revoked.')
            ->withCookie($this->trustedDevices->forgetCookie());
    }

    public function revokeTrustedDevice(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_unless($trustedDevice->user_id === $request->user()->id, 404);
        $isCurrent = $this->trustedDevices->current($request->user(), $request)?->is($trustedDevice) ?? false;
        $this->trustedDevices->revoke($request->user(), $trustedDevice, $request);
        $response = back()->with('status', 'The trusted device has been revoked. It will require 2FA on its next login.');

        return $isCurrent ? $response->withCookie($this->trustedDevices->forgetCookie()) : $response;
    }

    public function revokeOtherTrustedDevices(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $count = $this->trustedDevices->revokeOthers($request->user(), $request);

        return back()->with('status', $count > 0
            ? "{$count} other trusted device authorization(s) revoked."
            : 'There are no other trusted devices to revoke.');
    }

    private function requestEmailChange(User $user, string $newEmail): void
    {
        $token = Str::random(64);
        $user->forceFill([
            'pending_email' => $newEmail,
            'pending_email_token_hash' => hash('sha256', $token),
            'pending_email_expires_at' => now()->addMinutes(config('account-security.email_change_expire_minutes')),
        ])->save();

        try {
            Notification::route('mail', $newEmail)->notify(new VerifyNewEmail($user->id, $token));
        } catch (Throwable $exception) {
            $this->auditLogger->record('profile.email_delivery_failed', $user, $user, ['exception_type' => $exception::class]);
            $user->forceFill([
                'pending_email' => null,
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
            ])->save();

            throw ValidationException::withMessages(['email' => 'The verification message could not be delivered. Your current email was not changed.']);
        }
    }
}
