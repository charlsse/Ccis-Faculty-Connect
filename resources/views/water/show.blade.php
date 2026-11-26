@extends('layouts.dashboard')
@section('title', $collection->title)
@section('content')
<div class="water-module">
@include('water.partials.header', ['heading' => $collection->title])
@if($personalMember)<a class="button button-primary" href="{{ route('water.checkout', $personalMember) }}">My payment</a>@endif
@can('oversight', App\Models\WaterCollection::class)
<nav class="water-actions collection-sections" aria-label="Collection sections">
<a class="button" href="{{ route('water.show', $collection) }}" @if(request('section', 'overview') === 'overview') aria-current="page" @endif>Overview</a>
<a class="button" href="{{ route('water.show', [$collection, 'section' => 'members']) }}" @if(request('section') === 'members') aria-current="page" @endif>Members</a>
@can('manage', App\Models\WaterCollection::class)<a class="button" href="{{ route('water.queue', ['collection' => $collection->id]) }}">Verification</a>@endcan
<a class="button" href="{{ route('water.report', $collection) }}">Report & Excel export</a>
</nav>
@endcan
@if(request('section', 'overview') === 'overview')
<section class="water-card"><span class="water-badge">{{ ucfirst($collection->status) }}</span><p>{{ $collection->period }} · Due {{ $collection->due_date->format('F j, Y') }} · ₱{{ number_format($collection->amount, 2) }} per member</p><p class="water-prewrap">{{ $collection->description }}</p><small>Created {{ $collection->created_at->format('M j, Y') }} by {{ $collection->creator->full_name }}</small>
@can('manage', App\Models\WaterCollection::class)
<p>{{ $collection->members()->count() }} assigned members · QR account: {{ $collection->account->label }}</p><div class="water-actions">@if($collection->status === 'draft' && !$collection->published_at)<a class="button" href="{{ route('water.edit', $collection) }}">Edit draft</a>@endif<a class="button" href="{{ route('water.report', $collection) }}">Report & Excel export</a></div>
@if($collection->status !== 'archived')<form class="water-actions" method="POST" action="{{ route('water.transition', $collection) }}" data-water-confirm="Apply this collection status change?">@csrf @method('PATCH')<label>Change status<select name="status">@foreach((['draft'=>['published'],'published'=>['draft','closed'],'closed'=>['published','archived']][$collection->status] ?? []) as $next)<option value="{{ $next }}">{{ ['draft'=>'Unpublish','published'=>'Publish','closed'=>'Close','archived'=>'Archive'][$next] }}</option>@endforeach</select></label><label class="water-check"><input type="checkbox" name="confirmed" value="1" required>Confirm change</label><button class="button">Apply</button></form>@endif
@endcan</section>
@include('water.partials.summary')
@endif
@if(!auth()->user()->hasPermission('payments.view'))
<details class="water-card" ><summary>Official payment destination & instructions</summary><div class="water-payment-destination"><img class="water-qr" src="{{ route('water.qr', $collection) }}" alt="Official payment QR"><div><h2>{{ $collection->account->account_name }}</h2><p>{{ $collection->account->masked_account_number }}</p><p class="water-prewrap">{{ $collection->account->instructions }}</p><a class="button" href="{{ route('water.qr', [$collection, 'download' => 1]) }}">Download QR</a><ol><li>Download this QR to your phone.</li><li>Use a payment app that supports this QR. Import the saved image if your app supports it.</li><li>Verify the recipient name and masked number above, and enter the exact amount.</li><li>Complete payment in that app, then return here with the reference and proof.</li></ol><p>The QR image does not confirm payment. The Treasurer checks actual receiving-account transactions.</p></div></div></details>
@endif
@if(request('section') === 'members' || !auth()->user()->hasPermission('payments.view'))
@can('oversight', App\Models\WaterCollection::class)
<form class="water-card water-grid" method="GET"><input type="hidden" name="section" value="members"><label>Search member<input name="search" maxlength="100" value="{{ request('search') }}"></label><label>Payment status<select name="member_status"><option value="">All</option>@foreach(['paid'=>'Paid / Verified', 'pending'=>'Pending Verification', 'unpaid'=>'Unpaid', 'rejected'=>'Needs Attention', 'waived'=>'Waived'] as $value => $label)<option value="{{ $value }}" @selected(request('member_status') === $value)>{{ $label }}</option>@endforeach</select></label><button class="button">Filter members</button></form>
@endcan
<h2>{{ auth()->user()->hasPermission('payments.view') ? 'Assigned members' : 'Your payment & history' }}</h2>
@foreach($members as $member)<article class="water-card"><h3>{{ $member->user->full_name }}</h3><div class="water-actions"><span class="water-badge">{{ $member->status_label }}</span><strong>₱{{ number_format($member->assigned_amount, 2) }}</strong>@if($member->receipt)<a class="button" href="{{ route('water.receipt', $member->receipt) }}">Verified receipt</a>@endif</div>
@if($member->status === 'pending')<p role="status">Awaiting manual verification. This payment has not yet been officially recorded as paid.</p>@endif
@if($member->status === 'waived')<p>Waiver reason: {{ $member->waiver_reason }}</p>@endif
@if($member->status === 'rejected')<p class="water-alert">Payment needs attention: {{ $member->submissions->first()?->rejection_reason }} You may submit a new corrected attempt. Previous attempts are retained.</p>@endif
@if($member->user_id === auth()->id() && $collection->status === 'published' && in_array($member->status, ['unpaid','rejected']))<a class="button button-primary" href="{{ route('water.checkout', $member) }}">Pay Now</a>@endif
@can('manage', App\Models\WaterCollection::class)@if(in_array($member->status, ['unpaid','rejected']) && in_array($collection->status, ['published','closed']))<div class="water-actions"><a class="button" href="{{ route('water.cash', $member) }}">Record cash payment</a></div><details><summary>Authorize a waiver</summary><form class="water-form" method="POST" action="{{ route('water.waive', $member) }}" data-water-confirm="Waive this member's obligation? No payment receipt will be issued.">@csrf<label>Required waiver reason<textarea name="reason" required maxlength="2000"></textarea></label><label class="water-check"><input type="checkbox" name="confirmed" value="1" required>I authorize this waiver</label><button class="button">Record waiver</button></form></details>@endif @endcan
<details><summary>Full submission history ({{ $member->submissions->count() }})</summary>@forelse($member->submissions as $submission)<p><a href="{{ route('water.submission', $submission) }}">Attempt {{ $submission->attempt_number }} · {{ ucfirst($submission->status) }}</a> · {{ $submission->submitted_at->format('M j, Y H:i') }} {{ config('app.timezone') }}</p>@empty<p>No payments submitted.</p>@endforelse</details></article>@endforeach
@if($members->isEmpty())<p class="water-card">No members match these filters.</p>@endif
{{ $members->links() }}
@endif
</div>@endsection
