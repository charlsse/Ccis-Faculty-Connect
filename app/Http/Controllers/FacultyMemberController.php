<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdministratorService;
use App\Services\AuditLogger;
use App\Services\InvitationService;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FacultyMemberController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly AuditLogger $auditLogger,
        private readonly SessionManager $sessions,
    ) {}

    public function index(Request $request): View
    {
        $members = User::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('faculty_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.members.index', [
            'members' => $members,
            'search' => $request->string('search')->toString(),
            'roleFilter' => $request->string('role')->toString(),
            'statusFilter' => $request->string('status')->toString(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.members.create', ['currentDate' => now()->format('F j, Y')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $user = User::create([
            ...collect($validated)->except(['status', 'send_invitation'])->all(),
            'role' => $validated['role'],
            'status' => User::STATUS_PENDING,
            'password' => Hash::make(Str::random(64)),
        ]);
        $this->auditLogger->record('account.created', $user, $request->user(), [
            'role' => $user->role,
            'status' => $user->status,
        ]);

        if (! $request->boolean('send_invitation')) {
            return redirect()->route('members.show', $user)->with('status', 'Account created. Send an invitation when ready; login is unavailable until then.');
        }
        $delivered = $this->invitations->send($user, $request->user());
        $message = $delivered
            ? 'Account created. Invitation accepted by the mail transport; inbox delivery is not confirmed.'
            : 'Account created, but the invitation email was not delivered. Check mail settings and use Resend Invitation.';

        return redirect()->route('members.show', $user)->with($delivered ? 'status' : 'warning', $message);
    }

    public function show(User $member): View
    {
        return view('dashboard.members.show', [
            'member' => $member,
            'activities' => AuditLog::where('subject_user_id', $member->id)->latest('created_at')->limit(10)->get(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $validated = $request->validate($this->rules($member));
        $validated['role'] ??= $member->role;
        if ($validated['role'] !== $member->role || $validated['status'] !== $member->status) {
            $request->validate(['current_password' => ['required', 'current_password']]);
        }

        // Existing official email changes are handled by the user's verified change flow.
        if (strtolower((string) ($validated['email'] ?? null)) !== strtolower((string) $member->email)) {
            throw ValidationException::withMessages(['email' => 'Use the verified email-change flow in the user profile.']);
        }
        $setupIncomplete = $member->must_change_password
            || (! $member->password_replaced_at && ! $member->activated_at
                && ($member->status === User::STATUS_PENDING || $member->invitation_delivery_status));
        if ($validated['status'] === User::STATUS_ACTIVE && $setupIncomplete) {
            $validated['status'] = User::STATUS_PENDING;
        }
        app(AdministratorService::class)->update($member, $validated, $request->user());

        return back()->with('status', 'Account information has been updated.');
    }

    public function resendInvitation(Request $request, User $member): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $delivered = $this->invitations->send($member, $request->user());

        return back()->with($delivered ? 'status' : 'warning', $delivered
            ? 'New invitation accepted by the mail transport. Previous temporary credentials are invalid.'
            : 'Invitation failed. The account remains pending; retry sending.');
    }

    public function revokeAccess(Request $request, User $member): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $this->sessions->invalidate($member);
        $this->auditLogger->record('account.sessions_and_trust_revoked', $member, $request->user());

        return back()->with('status', 'Active sessions and trusted browsers have been revoked.');
    }

    public function sendPasswordReset(Request $request, User $member): RedirectResponse
    {
        abort_unless(! $member->must_change_password && ($member->status === User::STATUS_ACTIVE || ($member->status === User::STATUS_PENDING && $member->password_replaced_at)), 422, 'Issue an invitation for an account that has not chosen its permanent password.');

        $request->validate(['current_password' => ['required', 'current_password']]);
        try {
            $status = Password::sendResetLink(['email' => $member->email]);
        } catch (Throwable $exception) {
            // Delivery exception bodies may contain credentials; record only the class.
            $this->auditLogger->record('password_reset.delivery_failed', $member, $request->user(), ['exception_type' => $exception::class]);

            return back()->with('warning', 'The password-reset email could not be delivered.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('warning', __($status));
        }

        $this->auditLogger->record('password_reset.sent_by_admin', $member, $request->user());

        return back()->with('status', 'A password-reset link was sent.');
    }

    public function resetTwoFactor(Request $request, User $member): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_if($request->user()->is($member), 422, 'Another administrator must reset your two-factor registration.');
        $member->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
        $this->sessions->invalidate($member);
        $this->auditLogger->record('two_factor.reset_by_admin', $member, $request->user());

        return back()->with('status', 'Two-factor registration was reset. The user must enroll again after signing in.');
    }

    private function rules(?User $member = null): array
    {
        return [
            'send_invitation' => ['sometimes', 'boolean'],
            'faculty_id' => ['required', 'string', 'max:50', Rule::unique('users', 'faculty_id')->ignore($member?->id)],
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [$member?->isAcademicRosterDemo() ? 'nullable' : 'required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($member?->id)],
            'department' => ['required', 'string', 'max:180'],
            'position' => ['required', 'string', 'max:180'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'role' => [$member ? 'sometimes' : 'required', Rule::in(array_keys(config('roles')))],
            'status' => [$member ? 'required' : 'sometimes', Rule::in([User::STATUS_PENDING, User::STATUS_ACTIVE, User::STATUS_DEACTIVATED])],
        ];
    }
}
