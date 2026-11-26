@extends('layouts.dashboard')
@section('title', 'Water audit history')
@section('content')<div class="water-module">
@include('water.partials.header', ['heading' => 'Water audit history'])
<form class="water-card water-grid" method="GET"><label>Date<input type="date" name="date" value="{{ request('date') }}"></label><label>Action<input name="action" maxlength="100" placeholder="water.submitted" value="{{ request('action') }}"></label><label>Actor ID<input type="number" min="1" name="actor" value="{{ request('actor') }}"></label><button class="button">Filter activity</button><a href="{{ route('water.audit') }}">Reset</a></form>
<p>Audit metadata contains actor IDs, target IDs and state transitions. Private financial references and notes are excluded.</p>
@forelse($logs as $log)<article class="water-card"><h2>{{ $log->action }}</h2><p>{{ $log->created_at }} · Actor ID {{ $log->actor_user_id ?? 'System' }} · Subject ID {{ $log->subject_user_id ?? '—' }}</p><dl class="water-details">@foreach($log->details ?? [] as $key => $value)<dt>{{ $key }}</dt><dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>@endforeach</dl></article>@empty<p class="water-card">No water collection audit records yet.</p>@endforelse{{ $logs->links() }}
</div>@endsection
