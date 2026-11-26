@extends('layouts.dashboard')
@section('title', 'Collection editor')
@section('content')
<div class="water-module">
@include('water.partials.header', ['heading' => $collection->exists ? 'Edit draft' : 'Create collection'])
<form class="water-card water-form" method="POST" action="{{ $collection->exists ? route('water.update', $collection) : route('water.store') }}">@csrf @if($collection->exists)@method('PUT')@endif
<fieldset class="collection-group"><legend>1. Basic details</legend><label>Collection title<input name="title" maxlength="160" value="{{ old('title', $collection->title) }}" required></label>
<label>Collection category<select name="category"><option value="water" @selected(old('category', $collection->category ?? 'water') === 'water')>Water Contribution</option><option value="other" @selected(old('category', $collection->category) === 'other')>Other Faculty Club Collection</option></select></label>
<label>Description / instructions<textarea name="description" rows="4" maxlength="5000">{{ old('description', $collection->description) }}</textarea></label>
<label>Collection period<input type="month" name="period" value="{{ old('period', $collection->period ?? now()->format('Y-m')) }}" required></label></fieldset><fieldset class="collection-group"><legend>2. Payment</legend><p>Fixed amount per member. Partial or arbitrary amounts are not accepted.</p><div class="water-grid"><label>Required amount (PHP)<input name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount', $collection->amount) }}" required></label><label>Due date<input type="date" name="due_date" value="{{ old('due_date', $collection->due_date?->format('Y-m-d')) }}" required></label></div>
<label>Treasurer receiving account / QR<select name="payment_account_id" required>@forelse($accounts as $account)<option value="{{ $account->id }}" @selected(old('payment_account_id', $collection->payment_account_id) == $account->id)>{{ $account->label }} — {{ $account->account_name }} ({{ $account->masked_account_number }})</option>@empty<option value="">Create an active QR account first</option>@endforelse</select></label>
</fieldset><fieldset class="collection-group"><legend>3. Assignment</legend><label>Applicable members<select name="assignment"><option value="selected" @selected(old('assignment') !== 'all')>Selected active faculty members</option><option value="all" @selected(old('assignment') === 'all')>All active faculty members, including officers</option></select></label>
<fieldset><legend>Selected members (used only for selected assignment)</legend><div class="water-member-picker">@foreach($members as $member)<label class="water-check"><input type="checkbox" name="members[]" value="{{ $member->id }}" @checked(in_array($member->id, old('members', $collection->exists ? $collection->members()->pluck('user_id')->all() : [])))>{{ $member->full_name }} · {{ $member->role_label }}</label>@endforeach</div></fieldset>
</fieldset><p>Publishing makes these assignments visible. Financial terms and member assignments are locked after first publication.</p>
<button class="button button-primary" type="submit">Save draft</button><p>Next: review the saved details and assigned member count before publishing.</p>
</form></div>
@endsection
