@extends('layouts.dashboard')

@section('title', 'Security Settings')

@section('content')
    <section class="dashboard-hero content-hero"><div><p class="eyebrow">Account Protection</p><h1>Security Settings</h1><span>Manage your password, recovery codes, and active login sessions.</span></div></section>
    <section class="portal-panel privacy-entry"><div><p class="eyebrow">Documentation</p><h2>Privacy &amp; Security</h2><p>View policy publication status and security framework references.</p></div><a class="button button-secondary" href="{{ route('privacy.index') }}">Open documentation</a></section>
    @if (session('status'))<div class="dashboard-alert success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="dashboard-alert error" role="alert">Please review the highlighted security field.</div>@endif

    <div class="security-grid">
        <section class="content-editor-section">
            <div class="editor-section-heading"><p class="eyebrow">Password</p><h2>Change password</h2></div>
            <form class="announcement-form" method="POST" action="{{ route('security.password.update') }}">
                @csrf @method('PUT')
                <label>Current password<input type="password" name="current_password" autocomplete="current-password" required>@error('current_password') <span class="field-error">{{ $message }}</span> @enderror</label>
                @include('partials.new-password-fields')
                <div class="member-note">Use at least 12 characters with uppercase, lowercase, a number, and a symbol. Changing your password revokes other sessions.</div>
                <div class="form-actions"><button class="button button-primary" type="submit">Change Password</button></div>
            </form>
        </section>

        <aside class="profile-summary-card security-actions">
            <h2>Two-factor authentication</h2>
            <span class="status-pill {{ $user->two_factor_confirmed_at ? 'normal' : 'urgent' }}">{{ $user->two_factor_confirmed_at ? 'Enabled' : 'Setup required' }}</span>
            <p>Two-factor authentication is mandatory and cannot be disabled. An administrator can authorize a reset if you lose access.</p>
            @if ($user->two_factor_confirmed_at)
                <form class="announcement-form" method="POST" action="{{ route('security.recovery-codes.regenerate') }}" onsubmit="return confirm('Replace every existing recovery code?')">
                    @csrf
                    <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
                    <button class="button button-secondary" type="submit">Regenerate Recovery Codes</button>
                </form>
            @endif
        </aside>
    </div>

    <section class="portal-panel trusted-devices-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">Remembered Browsers</p><h2>Trusted Devices</h2></div>
        </div>
        <p class="member-note">A trusted browser may skip only the authenticator step for 20 days. Your email or employee ID and password are still required on every login.</p>

        <div class="trusted-device-list">
            @forelse($trustedDevices as $device)
                <article class="trusted-device-card {{ $device->revoked_at || $device->expires_at->isPast() ? 'expired' : '' }}">
                    <div class="trusted-device-details">
                        <div class="trusted-device-title">
                            <strong>{{ $device->device_name }}</strong>
                            @if($device->id === $currentTrustedDeviceId)<span class="status-pill normal">Current device</span>@endif
                            @if($device->revoked_at)<span class="status-pill urgent">Revoked</span>@elseif($device->expires_at->isPast())<span class="status-pill urgent">Expired</span>@else<span class="status-pill pending">Trusted</span>@endif
                        </div>
                        <span>First trusted {{ $device->first_trusted_at->format('M j, Y g:i A') }}</span>
                        <span>Last activity {{ $device->last_used_at?->diffForHumans() ?? 'Never' }} · Expires {{ $device->expires_at->format('M j, Y g:i A') }}</span>
                        <small>{{ $device->ip_address ?: 'Unknown IP' }} · {{ str($device->user_agent)->limit(140) }}</small>
                    </div>
                    @if(!$device->revoked_at && $device->expires_at->isFuture())
                        <form method="POST" action="{{ route('security.trusted-devices.destroy', $device) }}" onsubmit="return confirm('Revoke trust for this browser?')">
                            @csrf @method('DELETE')
                            <input type="password" name="current_password" placeholder="Current password" autocomplete="current-password" required aria-label="Current password">
                            <button class="button danger-button" type="submit">Revoke Trust</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state compact-empty"><h2>No trusted devices</h2><span>Select “Trust this device” after a successful 2FA challenge to add one.</span></div>
            @endforelse
        </div>

        <div class="trusted-device-actions">
            <form method="POST" action="{{ route('security.trusted-devices.destroy-others') }}" onsubmit="return confirm('Revoke all other trusted devices?')">
                @csrf @method('DELETE')
                <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
                <button class="button danger-button" type="submit">Revoke All Other Trusted Devices</button>
            </form>
            <form method="POST" action="{{ route('logout.forget-device') }}" onsubmit="return confirm('Log out and require 2FA again on this browser?')">
                @csrf
                <button class="button danger-button" type="submit">Log Out &amp; Forget This Device</button>
            </form>
        </div>
    </section>

    <section class="portal-panel audit-panel">
        <div class="panel-heading"><h2>Active login sessions</h2></div>
        @if ($sessions->isEmpty())
            <p class="member-note">Detailed session management is available when the database session driver is enabled.</p>
        @else
            <div class="session-list">
                @foreach($sessions as $session)
                    <article><div><strong>{{ $session->id === $currentSessionId ? 'Current session' : 'Browser session' }}</strong><span>{{ $session->ip_address ?: 'Unknown IP' }} · active {{ now()->setTimestamp($session->last_activity)->diffForHumans() }}</span><small>{{ str($session->user_agent)->limit(120) }}</small></div>
                        @if($session->id !== $currentSessionId)
                            <form method="POST" action="{{ route('security.sessions.destroy', $session->id) }}" onsubmit="return confirm('Revoke this session?')">@csrf @method('DELETE')<input type="password" name="current_password" placeholder="Current password" autocomplete="current-password" required aria-label="Current password"><button class="button danger-button" type="submit">Revoke</button></form>
                        @endif
                    </article>
                @endforeach
            </div>
            <form class="revoke-all-form" method="POST" action="{{ route('security.sessions.destroy-others') }}" onsubmit="return confirm('Revoke all other sessions?')">@csrf @method('DELETE')<label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><button class="button danger-button" type="submit">Revoke All Other Sessions</button></form>
        @endif
    </section>

    <section class="portal-panel audit-panel">
        <div class="panel-heading"><h2>Recent account-security activity</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Activity</th><th>Date &amp; time</th><th>IP address</th></tr></thead><tbody>
            @forelse($activities as $activity)<tr><td data-label="Activity">{{ str($activity->action)->replace(['.', '_'], ' ')->title() }}</td><td data-label="Date">{{ $activity->created_at->format('M j, Y g:i A') }}</td><td data-label="IP">{{ $activity->ip_address ?? '—' }}</td></tr>@empty<tr><td class="empty-table-cell" colspan="3">No security activity has been recorded.</td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
