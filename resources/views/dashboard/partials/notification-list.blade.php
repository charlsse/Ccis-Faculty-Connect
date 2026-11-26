@forelse($portalNotices->take(5) as $entry)
    @include('dashboard.partials.notification-item')
@empty
    <p class="notification-empty">You're all caught up. Payment, collection, announcement and security updates will appear here.</p>
@endforelse
