@extends('layouts.dashboard')
@section('title', request('view') === 'receipts' ? 'Receipts' : 'Transaction History')
@section('content')
<div class="water-module">
@include('water.partials.header', ['heading' => request('view') === 'receipts' ? 'Receipts' : (auth()->user()->hasPermission('payments.view') ? 'Transactions' : 'Transaction History')])
<div class="fc-section-intro"><p>{{ $oversight ? 'Review payment activity across the club.' : 'Track your payments and find your receipts.' }} Simulation entries do not count toward collected funds.</p><a class="button" href="{{ route('water.index') }}">View contributions</a></div>
<section class="water-card fc-history">
<form method="GET" class="fc-history-filters">
@if(request('view') === 'receipts')<input type="hidden" name="view" value="receipts">@endif
<label class="fc-search">Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Contribution or reference" maxlength="100"></label>
<label>Method<select name="method"><option value="">All methods</option>@foreach(['online'=>'Online Payment','qr'=>'Manual QR','cash'=>'Cash'] as $key=>$label)<option value="{{ $key }}" @selected(request('method') === $key)>{{ $label }}</option>@endforeach</select></label>
<label>Status<select name="status"><option value="">All statuses</option>@foreach(['pending','processing','completed','failed','expired','rejected'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></label>
<label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>
<div class="water-actions"><button class="button button-primary">Apply filters</button><a href="{{ route('water.demo.index', request('view') === 'receipts' ? ['view' => 'receipts'] : []) }}">Clear</a></div>
</form>
<p class="fc-result-count">{{ $transactions->total() }} {{ Str::plural('transaction', $transactions->total()) }}</p>
<div class="fc-transaction-list" role="table" aria-label="Transaction History">
<div class="fc-transaction-head" role="row"><span role="columnheader">Date / Contribution</span><span role="columnheader">Amount / Method</span><span role="columnheader">Status</span><span role="columnheader">Reference</span><span role="columnheader">Action</span></div>
@forelse($transactions as $transaction)
<article class="fc-transaction-row" role="row">
<div role="cell"><small>{{ $transaction['date']->timezone(config('app.timezone'))->format('M j, Y · H:i') }}</small><strong>{{ $transaction['title'] }}</strong><small>Faculty: {{ $transaction['faculty'] }}</small></div>
<div role="cell"><strong>PHP {{ number_format($transaction['amount'], 2) }}</strong><small>{{ ['online'=>'Online Payment','qr'=>'Manual QR','cash'=>'Cash'][$transaction['method']] }}</small></div>
<div role="cell"><span class="fc-status" data-status="{{ $transaction['status'] }}">{{ ucfirst($transaction['status']) }}</span>@if($transaction['simulation'])<small class="fc-simulation-tag">Simulation · No funds collected</small>@endif</div>
<div class="fc-reference" role="cell">{{ $transaction['reference'] }}</div>
<div class="fc-row-actions" role="cell"><a href="{{ $transaction['url'] }}">View details →</a>@if($transaction['receipt'])<a href="{{ $transaction['receipt'] }}">View Receipt</a>@endif</div>
</article>
@empty<div class="fc-empty"><h2>No transactions found</h2><p>Try a different filter, or open a contribution to make your first payment.</p><a class="button" href="{{ route('water.index') }}">View contributions</a></div>@endforelse
</div>
{{ $transactions->links() }}
</section></div>
@endsection
