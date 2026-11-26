@extends('layouts.dashboard')
@section('title', 'Online payment status')
@section('content')
<div class="water-module water-checkout">
@include('water.partials.header', ['heading' => 'Online payment status', 'focused' => true])
<section class="water-card fc-payment-result" data-online-status="{{ route('water.online.status', $attempt) }}" data-state="{{ $attempt->status }}">
    <span class="water-badge">Simulation - No Funds Collected</span>
    <h2>{{ $attempt->member->collection->title }}</h2><strong class="checkout-amount">₱{{ number_format($attempt->amount_minor / 100, 2) }}</strong>
    <p class="water-reference">Reference: {{ $attempt->display_reference }}</p>
    @if($attempt->status === 'confirmed')
        <h2>Payment Completed</h2><p>Your simulated payment is verified. Actual contribution dues remain unchanged.</p>
        @if($attempt->member->receipt)<a class="button button-primary" href="{{ route('water.receipt', $attempt->member->receipt) }}">View Receipt</a>@endif
    @elseif($attempt->status === 'expired')
        <h2>Payment Expired</h2><p>The provider confirms that this checkout has expired without a successful payment. You can try again or pay manually.</p>
        <a class="button button-primary" href="{{ route('water.checkout', $attempt->member) }}">Try Again / Pay Manually</a>
    @elseif($attempt->status === 'attention')
        <h2>Payment needs investigation</h2><p>The payment details could not be safely matched to this contribution. Contact the Treasurer with the reference above. Do not pay again.</p>
    @else
        <h2 data-online-heading>Payment Processing</h2><p data-online-message role="status">We're waiting for confirmation from the payment provider. Do not pay again yet.</p>
        @if($attempt->status === 'unknown' || !$attempt->provider_session_id)<p>The checkout response was not confirmed. The Treasurer can reconcile this reference; no duplicate checkout will be created automatically.</p>@endif
        @if($attempt->status === 'pending' && \App\Http\Controllers\WaterGatewayController::safeCheckout($attempt))<a class="button" href="{{ $attempt->checkout_url }}">Resume Secure Checkout</a>@endif
    @endif
    @if($attempt->status !== 'confirmed')<form method="POST" action="{{ route('water.online.check', $attempt) }}">@csrf<button class="button" type="submit">Check Again</button></form>@endif
    <a class="button" href="{{ route('water.index') }}">Return to Payments</a>
</section>
</div>
<script src="{{ asset('js/water-online.js') }}" defer></script>
@endsection
