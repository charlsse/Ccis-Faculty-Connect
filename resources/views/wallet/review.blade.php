@extends('layouts.dashboard')
@section('title','Review simulated withdrawal')
@section('content')
<link rel="stylesheet" href="{{ asset('css/wallet.css') }}">
<section class="wallet-workspace wallet-review"><a href="{{ route('wallet.index') }}">← Treasurer Wallet</a><div class="wallet-panel"><span class="wallet-badge">SIMULATED · NO MONEY WILL BE SENT</span><h1>Review withdrawal</h1><dl class="wallet-totals"><div><dt>Collection</dt><dd>{{ $c->title }}</dd></div><div><dt>Locked destination</dt><dd>{{ $d->label }} · {{ $d->masked_number }}</dd></div><div><dt>Amount</dt><dd>₱{{ number_format($intent['amount']/100,2) }}</dd></div><div><dt>Simulation fee</dt><dd>₱0.00</dd></div></dl><p>Review expires in five minutes. Funds are reserved only after confirmation. This is not a Xendit withdrawal request.</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('wallet.confirm') }}" class="wallet-form wallet-stack">@csrf<input type="hidden" name="key" value="{{ $intent['key'] }}"><label>Confirm with your password<input type="password" name="password" autocomplete="current-password" required></label><button>Confirm simulated withdrawal</button></form></div></section>
@endsection
