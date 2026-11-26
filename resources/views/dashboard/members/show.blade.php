@extends('layouts.dashboard')

@section('title', $member->full_name)

@section('content')
    <section class="dashboard-hero content-hero">
        <div><p class="eyebrow">Account Record</p><h1>{{ $member->full_name }}</h1><span>{{ $member->faculty_id }} · {{ $member->role_label }}</span></div>
        <a class="button button-secondary" href="{{ route('members.index') }}">Back to Accounts</a>
    </section>
    @if (session('status'))<div class="dashboard-alert success" role="status">{{ session('status') }}</div>@endif
    @if (session('warning'))<div class="dashboard-alert error" role="alert">{{ session('warning') }}</div>@endif
    @if ($errors->any())<div class="dashboard-alert error" role="alert">Please review the highlighted fields and try again.</div>@endif

    <div class="security-grid">
        <section class="content-editor-section">
            <div class="editor-section-heading"><p class="eyebrow">Administrator Tools</p><h2>Edit account information</h2></div>
            <form class="announcement-form" method="POST" action="{{ route('members.update', $member) }}">
                @csrf @method('PATCH')
                @include('dashboard.members.partials.form', ['member' => $member])
                <label>Administrator password <small>Required for role or status changes</small><input type="password" name="current_password" autocomplete="current-password">@error('current_password')<span class="field-error">{{ $message }}</span>@enderror</label>
                <div class="form-actions"><button class="button button-primary" type="submit">Save Account</button></div>
            </form>
        </section>

        <aside class="profile-summary-card security-actions">
            <h2>Access &amp; security</h2>
            <form method="POST" action="{{ route('members.sessions.revoke', $member) }}" onsubmit="return confirm('End sessions and revoke browser trust?')">@csrf @method('DELETE')<label>Administrator password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="button danger-button">Revoke sessions &amp; trusted browsers</button></form>
            <div class="profile-facts">
                <span>Invitation <strong>{{ $member->invitation_delivery_status ?? 'Not sent' }}</strong></span>
                <span>Last invitation <strong>{{ $member->invitation_sent_at?->format('M j, Y g:i A') ?? 'Never' }}</strong></span>
                <span>Temporary password expires <strong>{{ $member->temporary_password_expires_at?->format('M j, Y g:i A') ?? 'Not applicable' }}</strong></span>
                <span>Password replacement <strong>{{ $member->must_change_password ? 'Required' : ($member->password_replaced_at ? 'Completed' : 'Existing password') }}</strong></span>
                <span>Email <strong>{{ $member->isAcademicRosterDemo() ? 'Not supplied; academic demo uses authenticator enrollment' : ($member->email_verified_at ? 'Verified' : 'Verification required') }}</strong></span>
                <span>Onboarding <strong>{{ $member->onboarding_completed_at ? 'Completed' : 'Security checks required at login' }}</strong></span>
                <span>Created <strong>{{ $member->created_at->format('F j, Y g:i A') }}</strong></span>
                <span>Last accessed <strong>{{ $member->last_login_at?->format('F j, Y g:i A') ?? 'Never' }}</strong></span>
                <span>2FA <strong>{{ $member->two_factor_confirmed_at ? 'Enabled' : 'Setup required' }}</strong></span>
            </div>
            @if ($member->status === 'pending_activation' && ! $member->activated_at && ! $member->password_replaced_at)
                <form method="POST" action="{{ route('members.invitation', $member) }}" onsubmit="return confirm('Issue new temporary credentials and invalidate the previous invitation?')">@csrf<label>Administrator password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="button button-secondary" type="submit">Send / Resend Invitation</button></form>
            @endif
            @if (! $member->must_change_password && ($member->status === 'active' || ($member->status === 'pending_activation' && $member->password_replaced_at)))
                <form method="POST" action="{{ route('members.password-reset', $member) }}">@csrf<label>Administrator password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="button button-secondary" type="submit">Send Password Reset</button></form>
            @endif
            @if ($member->two_factor_confirmed_at && ! auth()->user()->is($member))
                <form method="POST" action="{{ route('members.two-factor.reset', $member) }}" onsubmit="return confirm('Reset this user’s 2FA and revoke every active session?')">
                    @csrf @method('DELETE')
                    <label>Confirm your administrator password<input type="password" name="current_password" autocomplete="current-password" required>@error('current_password') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <button class="button danger-button" type="submit">Reset 2FA Registration</button>
                </form>
            @endif
        </aside>
    </div>

    <section class="portal-panel audit-panel">
        <div class="panel-heading"><h2>Recent security activity</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Action</th><th>Actor</th><th>Date &amp; time</th><th>IP address</th></tr></thead><tbody>
            @forelse($activities as $activity)<tr><td data-label="Action">{{ str($activity->action)->replace(['.', '_'], ' ')->title() }}</td><td data-label="Actor">{{ $activity->actor?->full_name ?? 'System / Visitor' }}</td><td data-label="Date">{{ $activity->created_at->format('M j, Y g:i A') }}</td><td data-label="IP">{{ $activity->ip_address ?? '—' }}</td></tr>@empty<tr><td class="empty-table-cell" colspan="4">No account activity has been recorded.</td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
