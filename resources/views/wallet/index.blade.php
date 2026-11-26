@extends('layouts.dashboard')
@section('title','Treasurer Wallet · TEST')
@section('content')
<link rel="stylesheet" href="{{ asset('css/wallet.css') }}">
@php($money=fn($minor)=>'₱'.number_format($minor/100,2))
@php($manage=auth()->user()->role==='treasurer')
<section class="wallet-workspace">
<header class="wallet-heading"><div><span class="wallet-eyebrow">PAYMENTS & COLLECTIONS / SIMULATION</span><h1>Treasurer Wallet <span class="wallet-badge">TEST</span></h1><p>Track collected funds, settlement and transfers in one place.</p></div><a class="wallet-link" href="{{ route('water.index') }}">Contributions & receipts →</a></header>
@include('water.partials.navigation')
<div class="wallet-notice">Simulated balances and transfers only. No bank funds are available here. {{ $manage ? 'Use the simulator controls to explore each outcome.' : 'Financial oversight · view only.' }}</div>
<p class="wallet-muted">Accounting reconciliation: {{ $reconciliation['matches'] ? 'Matched · settlement and withdrawal records agree with the ledger' : 'Mismatch · new requests blocked; investigation required' }}.</p>
@if(session('status'))<p role="status" class="wallet-notice">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="wallet-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="wallet-metrics">
<article><small>Verified contributions</small><strong>{{ $money($methods->sum()) }}</strong><span>Historical receipt total, never reduced by transfers</span></article>
<article class="wallet-feature"><small>Available · SIMULATED</small><strong>{{ $money($balance['available']) }}</strong><span>Settled net funds, after reservations</span></article>
<article><small>Pending settlement</small><strong>{{ $money($balance['pending']) }}</strong><span>Not yet available to request</span></article>
<article><small>Reserved for transfers</small><strong>{{ $money($balance['reserved']) }}</strong><span>Pending and processing requests</span></article>
</div>
<div class="wallet-grid"><section class="wallet-panel"><h2>Collection targets</h2><p class="wallet-muted">FacultyConnect eligibility rule. Xendit does not enforce these targets.</p>
@foreach($collections as $c)
<article class="wallet-collection"><div class="wallet-row"><div><h3>{{ $c->title }}</h3><small>{{ $c->period }} · Outstanding {{ $money($c->outstanding_minor) }}</small></div><span class="wallet-badge">{{ $c->target_minor>0 && $c->collected_minor >= $c->target_minor ? 'Target reached' : 'Building funds' }}</span></div>
<progress max="{{ max(1,$c->target_minor) }}" value="{{ min($c->collected_minor,max(1,$c->target_minor)) }}" aria-label="{{ $c->title }} target progress"></progress>
<div class="wallet-row"><span>{{ $money($c->collected_minor) }} / {{ $c->target_minor ? $money($c->target_minor) : 'Target not set' }}</span><small>Available {{ $money($c->wallet['available']) }}</small></div>
@if($manage)<details><summary>Adjust target</summary><form method="post" action="{{ route('wallet.target') }}" class="wallet-form">@csrf<input type="hidden" name="collection" value="{{ $c->id }}"><label>Target (PHP)<input name="amount" inputmode="decimal" value="{{ number_format($c->target_minor/100,2,'.','') }}" required></label><button>Save target</button></form></details>@endif</article>
@endforeach</section>
<section class="wallet-panel"><h2>{{ $manage ? 'Request a simulated withdrawal' : 'Funds overview' }}</h2>
@if($manage)<form method="post" action="{{ route('wallet.review') }}" class="wallet-form wallet-stack">@csrf
<label>Collection<select name="collection" required>@foreach($collections as $c)<option value="{{ $c->id }}" @disabled($c->target_minor<=0 || $c->collected_minor<$c->target_minor || $c->wallet['available']<=0)>{{ $c->title }} · {{ $money($c->wallet['available']) }}</option>@endforeach</select></label>
<label>Approved simulation destination<select name="destination" required>@foreach($destinations as $d)<option value="{{ $d->id }}">{{ $d->label }} · {{ $d->masked_number }}</option>@endforeach</select></label>
<label>Amount (PHP)<input name="amount" inputmode="decimal" placeholder="0.00" required></label><button>Review simulated withdrawal →</button><small>No real account routing is stored. Confirmation requires your password.</small></form>@endif
<h3>Payment-method breakdown</h3><dl class="wallet-totals">@foreach($methods as $method=>$amount)<div><dt>{{ $method==='xendit_test' ? 'Xendit TEST · verified receipts' : $method.' · outside provider wallet' }}</dt><dd>{{ $money($amount) }}</dd></div>@endforeach
<div><dt>Net completed transfers · simulated</dt><dd>{{ $money($balance['withdrawn']) }}</dd></div><div><dt>Fees · simulated</dt><dd>{{ $money($balance['fees']) }}</dd></div></dl>
<p class="wallet-muted">Actual Xendit balance: not connected or verified. Manual QR/cash does not fund this simulated provider wallet.</p>
</section></div>
<section class="wallet-panel"><div class="wallet-row"><h2>Withdrawal history</h2><span class="wallet-badge">ALL TRANSFERS SIMULATED</span></div><div class="wallet-table"><table><thead><tr><th>Reference / destination</th><th>Amount</th><th>Status</th><th>Updated</th>@if($manage)<th>Simulation controls</th>@endif</tr></thead><tbody>
@forelse($withdrawals as $w)<tr><td><code>{{ $w->reference }}</code><small>{{ $w->destination_snapshot }}</small></td><td>{{ $money($w->amount_minor) }}</td><td><span class="wallet-badge">{{ ucfirst($w->status) }}</span></td><td>{{ $w->updated_at }}</td>@if($manage)<td>@php($next=match($w->status){'pending'=>['processing','failed'],'processing'=>['completed','failed'],'completed'=>['reversed'],default=>[]})@foreach($next as $outcome)<form class="wallet-inline" method="post" action="{{ route('wallet.simulate',$w->id) }}">@csrf<input type="hidden" name="outcome" value="{{ $outcome }}"><button class="wallet-secondary">Simulate {{ $outcome }}</button></form>@endforeach</td>@endif</tr>@empty<tr><td colspan="5">No simulated withdrawal requests yet.</td></tr>@endforelse
</tbody></table></div>{{ $withdrawals->links() }}</section>
<div class="wallet-grid"><section class="wallet-panel"><h2>Pending provider settlements · simulated</h2>@forelse($settlements as $s)<div class="wallet-row"><span>Receipt #{{ $s->receipt_id }} · {{ $money($s->gross_minor-$s->fee_minor) }} net</span>@if($manage)<form method="post" action="{{ route('wallet.settle',$s->id) }}">@csrf<button class="wallet-secondary">Simulate settlement</button></form>@endif</div>@empty<p>No pending simulated settlements.</p>@endforelse</section>
<section class="wallet-panel"><h2>Recent wallet activity</h2>@foreach($activity as $e)<div class="wallet-activity"><span>{{ $e->kind }}</span><small>{{ $e->created_at }}</small></div>@endforeach</section></div>
</section>
@endsection
