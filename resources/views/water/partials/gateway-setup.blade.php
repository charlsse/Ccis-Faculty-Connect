@if(config('payment_gateway.enabled') === true && auth()->user()->isAdmin())
@php($gatewayReady = app(\App\Services\Payments\WaterGatewayService::class)->available())
<section class="water-card" aria-label="Online test checkout">
    <span class="water-badge">OPTIONAL TEST MODE</span>
    <h2>{{ $gatewayReady ? 'Online test checkout is available' : 'Online test checkout is not active' }}</h2>
    <p>Eligible faculty can use the provider simulator for assigned test contributions. Test receipts are not valid for real contribution dues.</p>
    <p>Manual payments and Treasurer verification remain available.</p>
</section>
@endif
