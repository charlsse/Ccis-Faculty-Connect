@extends('layouts.dashboard')
@section('title', 'Payments & Collections')
@section('content')
<div class="water-module">
@php
    $oversight = auth()->user()->hasPermission('payments.view');
    $collectionView = $oversight && (request('section') === 'collections' || request('category'));
    $heading = $collectionView ? 'Collections' : ($oversight ? (auth()->user()->hasPermission('payments.manage') ? 'Payments Overview' : 'Financial Overview') : 'My Payments');
@endphp
@include('water.partials.header', ['heading' => $heading])
@if(!$collectionView)
    @can('oversight', App\Models\WaterCollection::class)
    <div class="section-heading"><h2>Collection summary</h2><a href="{{ route('water.reports') }}">View reports & exports</a></div>
    @include('water.partials.summary')
    <div class="payment-overview-actions">
        @can('manage', App\Models\WaterCollection::class)<a class="button button-primary" href="{{ route('water.create') }}">Create Collection</a><a class="button" href="{{ route('water.queue') }}">Pending Verification</a>@endcan
        <a class="button" href="{{ route('water.index', ['section' => 'collections']) }}">View all collections</a>
        <a class="button" href="{{ route('water.demo.index') }}">All transactions</a>
    </div>
    <section class="water-card payment-overview-activity" aria-labelledby="recent-payment-heading">
        <h2 id="recent-payment-heading">Recent payment activity</h2>
        @forelse($recentPayments as $submission)
        <article class="payment-activity-row"><div><strong>{{ $submission->member->collection->title }}</strong><small>{{ $submission->submitted_at?->format('M j, Y') }} · {{ ucfirst($submission->status) }}@if(in_array($submission->payment_method, ['xendit_test', 'paymongo_test'])) · Simulation — No funds collected @endif</small></div><a class="button" href="{{ route('water.submission', $submission) }}">View details</a></article>
        @empty<p>No payment submissions yet. New activity will appear here.</p>@endforelse
    </section>
    @endcan
    <div class="section-heading"><h2>My contributions</h2><a href="{{ route('water.demo.index') }}">Transaction History</a></div>
    <section class="water-grid">
    @forelse($personalWater->whereIn('status', ['unpaid', 'rejected', 'pending']) as $obligation)
        @php($online = \App\Services\Payments\WaterGatewayService::active($obligation))
        <article class="water-card payment-due-card"><p class="eyebrow">{{ $obligation->collection->period }}</p><h2>{{ $obligation->collection->title }}</h2>
            <strong class="checkout-amount">₱{{ number_format($obligation->assigned_amount, 2) }}</strong><p>Due {{ $obligation->collection->due_date->format('F j, Y') }}</p>
            <span class="water-badge" data-status="{{ $obligation->status }}">{{ $obligation->status_label }}</span>
            <a class="button button-primary" href="{{ $online ? route('water.online.show', $online) : route('water.checkout', $obligation) }}">{{ $online ? 'Check online payment' : ($obligation->status === 'pending' ? 'View pending payment' : ($obligation->collection->status === 'published' ? ($obligation->status === 'rejected' ? 'Resubmit Payment Proof' : 'Pay Now') : 'View payment')) }}</a>
        </article>
    @empty<p class="water-card">You're all caught up. There are no outstanding contributions right now.</p>@endforelse
    </section>

@else
    <div class="section-heading"><h2>Collections Overview</h2>@can('manage', App\Models\WaterCollection::class)<a class="button button-primary" href="{{ route('water.create') }}">Create collection</a>@endcan</div>
    <form method="GET" class="water-actions"><input type="hidden" name="section" value="collections">@if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif<label>Collection status<select name="status"><option value="">All statuses</option>@foreach(['draft','published','closed','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label><button class="button" type="submit">Filter</button></form>
    <section class="water-grid">@forelse($collections as $collection)<article class="water-card"><span class="water-badge">{{ ucfirst($collection->status) }}</span><h2><a href="{{ route('water.show', $collection) }}">{{ $collection->title }}</a></h2><p>{{ $collection->period }} · Due {{ $collection->due_date->format('M j, Y') }}</p><strong>₱{{ number_format($collection->amount, 2) }} / member</strong><a class="button" href="{{ route('water.show', $collection) }}">View collection</a></article>@empty<p class="water-card">No collections in this view. Create a draft to get started.</p>@endforelse</section>
    {{ $collections->links() }}
@endif
<details class="water-card"><summary>Collection notifications</summary>@forelse($notices as $notice)<article class="water-notice"><p>{{ $notice->message }}</p><small>{{ $notice->created_at->format('M j, Y H:i') }} {{ config('app.timezone') }} · {{ $notice->read_at ? 'Read' : 'Unread' }}</small><div class="water-actions"><a href="{{ route('water.show', $notice->water_collection_id) }}">Open collection</a>@unless($notice->read_at)<form method="POST" action="{{ route('water.notices.read', $notice) }}">@csrf<button type="submit">Mark read</button></form>@endunless</div></article>@empty<p>No notifications yet.</p>@endforelse</details>
</div>
@endsection
