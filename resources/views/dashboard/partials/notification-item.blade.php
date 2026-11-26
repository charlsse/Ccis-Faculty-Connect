<article class="notification-item {{ $entry['read_at'] ? '' : 'is-unread' }}">
    <span class="notice-symbol" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        @if($entry['category'] === 'collections')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/>
        @elseif($entry['category'] === 'announcements')<path d="m4 9 16-5v16L4 15V9Z M4 10H2v4h2 M7 16l2 5h3l-2-4"/>
        @else<path d="m12 3 8 3v6c0 5-8 9-8 9S4 17 4 12V6l8-3Z M12 8v5 M12 16h.01"/>@endif
    </svg></span>
    <div class="notice-copy">
        <strong>{{ $entry['title'] }}</strong>
        <p>{{ $entry['message'] }}</p>
        <small><time datetime="{{ $entry['created_at']->toIso8601String() }}" title="{{ $entry['created_at']->format('F j, Y g:i A') }}">{{ $entry['created_at']->diffForHumans() }}</time> · {{ $entry['read_at'] ? 'Read' : 'Unread' }}</small>
        <div class="notice-actions">
            <form method="POST" action="{{ route('notifications.read', $entry['id']) }}">@csrf<input type="hidden" name="open" value="1"><button type="submit">{{ ['collections' => 'View collection', 'announcements' => 'View announcement', 'security' => 'Review security'][$entry['category']] }} <span aria-hidden="true">&rarr;</span></button></form>
            @unless($entry['read_at'])<form method="POST" action="{{ route('notifications.read', $entry['id']) }}">@csrf<button type="submit">Mark as read</button></form>@endunless
        </div>
    </div>
</article>
