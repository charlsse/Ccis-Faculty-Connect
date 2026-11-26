@extends('layouts.dashboard')
@section('title', 'Notifications')
@section('content')
<section class="dashboard-hero"><div><p class="eyebrow">Your workspace</p><h1>Notifications</h1><span>Your collection, announcement and account security updates.</span></div>
<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="button button-secondary" type="submit">Mark all as read</button></form></section>
@if(session('status'))<div class="dashboard-alert success" role="status">{{ session('status') }}</div>@endif
<nav class="notice-filters" aria-label="Filter notifications">
@foreach(['all' => 'All', 'unread' => 'Unread', 'collections' => 'Collections', 'announcements' => 'Announcements', 'security' => 'Security'] as $value => $label)
<a href="{{ route('notifications.index', ['filter' => $value]) }}" @if($filter === $value) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
<section class="notification-history" aria-label="Notification history">
@forelse($entries as $entry) @include('dashboard.partials.notification-item')
@empty <p class="notification-empty">No notifications in this category.</p> @endforelse
</section>
{{ $entries->links() }}
@endsection
