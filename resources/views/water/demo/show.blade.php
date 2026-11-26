@extends('layouts.dashboard')
@section('title', 'Payment Status')
@section('content')
<div class="water-module">
@include('water.partials.header', ['heading' => 'Payment Status', 'focused' => true])
<section class="water-card fc-payment-result" data-online-status="{{ route('water.demo.status', $attempt) }}" data-state="{{ $attempt->status }}">
<header class="fc-result-header"><span class="fc-result-symbol" aria-hidden="true">{{ $attempt->status === 'confirmed' ? '✓' : ($attempt->status === 'expired' ? '◷' : '↻') }}</span>
<h2 data-online-heading>{{ match($attempt->status) { 'confirmed' => 'Payment Completed', 'expired' => 'Payment Expired', 'failed' => 'Payment Unsuccessful', default => 'Payment Processing' } }}</h2>
<p>{{ $attempt->contribution_title }} · {{ $attempt->billing_period }}</p><div class="fc-amount"><strong>PHP {{ number_format($attempt->amount_minor / 100, 2) }}</strong></div>
<span class="fc-status" data-status="{{ $attempt->status === 'confirmed' ? 'completed' : $attempt->status }}">{{ $attempt->status === 'confirmed' ? 'Completed' : (in_array($attempt->status, ['expired','failed']) ? ucfirst($attempt->status) : 'Processing') }}</span>
</header>
<dl class="water-details"><dt>Transaction reference</dt><dd>{{ $attempt->display_reference }}</dd><dt>Payment method</dt><dd>Online Payment</dd><dt>Payment date</dt><dd>{{ $attempt->paid_at?->timezone(config('app.timezone'))->format('F j, Y · H:i') ?? 'Awaiting confirmation' }}</dd><dt>Time zone</dt><dd>{{ config('app.timezone') }}</dd></dl>
<p class="fc-simulation-note">Simulation — No Funds Collected. Your actual FacultyConnect contribution remains unchanged.</p>
<div class="water-actions">
@if($attempt->status === 'confirmed')
<a class="button button-primary" href="{{ route('water.demo.receipt', $attempt) }}">View Receipt</a><a class="button" href="{{ route('water.demo.receipt', [$attempt, 'download'=>1]) }}">Download PDF</a>
@elseif(in_array($attempt->status, ['expired','failed']))
<p>This payment did not complete. Return to the contribution to review your options.</p>
@else
<p data-online-message role="status">We are waiting for verified payment confirmation. Check the existing payment instead of starting another.</p>
@if($attempt->provider_session_id)<form method="POST" action="{{ route('water.demo.check', $attempt) }}">@csrf<button class="button button-primary">Check Payment Status</button></form>@else<p>Checkout could not be confirmed. Please contact the Treasurer before trying again.</p>@endif
@if($attempt->status === 'pending' && auth()->id() === $attempt->user_id)
<form method="POST" action="{{ route('water.demo.start', $attempt->water_member_id) }}">@csrf<input type="hidden" name="test_mode_confirmed" value="1"><button class="button">Resume Secure Checkout</button></form>
@endif
@endif
@if(auth()->id() === $attempt->user_id)<a class="button" href="{{ route('water.checkout', $attempt->water_member_id) }}">Back to contribution</a>@endif
</div><div class="water-actions"><a href="{{ route('water.demo.index') }}">Transaction History</a><a href="{{ route('water.index') }}">Payments & Collections</a></div>
</section></div>
<script src="{{ asset('js/water-online.js') }}" defer></script>
@endsection
