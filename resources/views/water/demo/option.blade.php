@php($demoReady = app(\App\Services\Payments\XenditDemoService::class)->ready())
<div class="fc-online-option">
    <div class="fc-method-heading"><span class="fc-method-icon" aria-hidden="true">@include('partials.ui-arrow')</span><div><h2>Online Payment</h2><p>Continue to secure online checkout.</p></div><span class="fc-selected">Selected</span></div>
    @if($demoReady || $onlineAvailable)
    <form method="POST" action="{{ route($demoReady ? 'water.demo.start' : 'water.online.start', $member) }}" data-gateway-start>@csrf
        <label class="water-check fc-simulation-consent"><input type="checkbox" name="test_mode_confirmed" value="1" required> I understand this is a simulation. No funds will be collected or applied to my dues.</label>
        <button class="button button-primary fc-pay-button" type="submit">Continue to Payment @include('partials.ui-arrow', ['direction' => 'right'])</button>
        <p class="fc-checkout-feedback" role="status" aria-live="polite"></p>
    </form>
    @else
        <p role="status">Online payment is temporarily unavailable. You can choose Manual QR Payment below.</p><button class="button" disabled>Continue to Payment</button>
    @endif
</div>
