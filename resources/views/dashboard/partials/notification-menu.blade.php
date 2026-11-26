@php
    $portalNotices = app(\App\Services\PortalNotifications::class)->entries(auth()->user());
    $unreadCount = $portalNotices->whereNull('read_at')->count();
@endphp
<div class="notification-menu" data-notification-feed="{{ route('notifications.feed') }}" data-payment-auto-refresh="{{ request()->routeIs('water.index', 'water.demo.index', 'wallet.index', 'dashboard', 'water.reports') ? 'true' : 'false' }}">
    <button type="button" class="icon-button notification-button" data-notification-toggle aria-expanded="false" aria-controls="notification-panel" aria-label="Notifications, {{ $unreadCount }} unread">
        <span class="notification-mark" aria-hidden="true"></span>
        <span class="notification-count" data-unread-count aria-hidden="true" @if(!$unreadCount) hidden @endif>{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
    </button>
    <section class="notification-panel fc-liquid-glass fc-glass-elevated" id="notification-panel" data-notification-panel hidden aria-label="Notifications">
        <div class="notification-heading"><h2>Notifications</h2>
            <form method="POST" action="{{ route('notifications.read-all') }}" data-read-all @if(!$unreadCount) hidden @endif>@csrf<button type="submit">Mark all as read</button></form>
        </div>
        <div class="notification-list" data-notification-list aria-live="polite">
            @include('dashboard.partials.notification-list')
        </div>
        <a class="notification-footer" href="{{ route('notifications.index') }}">View all notifications <span aria-hidden="true">&rarr;</span></a>
    </section>
</div>
<script src="{{ asset('js/payment-notifications.js') }}" defer></script>
