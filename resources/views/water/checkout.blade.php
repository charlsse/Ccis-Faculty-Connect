@extends('layouts.dashboard')
@section('title', 'Complete Payment')
@section('content')
<div class="water-module fc-payment-checkout">
    @include('water.partials.header', ['heading' => 'Complete Payment', 'focused' => true])
    <a class="fc-back" href="{{ route('water.index') }}">← Payments & Collections</a>
    <div class="fc-checkout-layout">
    <section class="water-card fc-order-summary">
        <p class="eyebrow">Contribution details</p><h2>{{ $collection->title }}</h2>
        <p>{{ $collection->period }}</p><div class="fc-amount"><small>Amount due</small><strong>PHP {{ number_format(in_array($member->status, ['paid','waived']) ? 0 : $member->assigned_amount, 2) }}</strong></div>
        <dl class="water-details"><dt>Due date</dt><dd>{{ $collection->due_date?->format('F j, Y') ?? 'Not specified' }}</dd><dt>Contribution status</dt><dd><span class="water-badge" data-status="{{ $member->status }}">{{ $member->status_label }}</span></dd></dl>
        <a href="{{ route('water.demo.index') }}">Transaction History →</a>
    </section>
    <section class="water-card fc-payment-methods">
    @if($member->status === 'pending')
        <h2>Payment awaiting review</h2><p>Your proof is with the Treasurer. No further payment is needed.</p>
        @if($member->latestSubmission)<a class="button button-primary" href="{{ route('water.submission', $member->latestSubmission) }}">View Payment</a>@endif
    @elseif(in_array($member->status, ['paid','waived']))
        <h2>{{ $member->status === 'paid' ? 'Payment Completed' : 'No payment required' }}</h2>
        @if($member->receipt)<a class="button button-primary" href="{{ route('water.receipt', $member->receipt) }}">View Receipt</a>@endif
    @elseif($collection->status !== 'published')
        <h2>Payments are closed</h2><p>Contact the Treasurer for assistance with this contribution.</p>
    @elseif($onlineAttempt)
        <h2>Payment Processing</h2><p>An existing online payment is awaiting confirmation. Check its status before paying again.</p><a class="button button-primary" href="{{ route('water.online.show', $onlineAttempt) }}">View Payment Status</a>
    @else
        <p class="eyebrow">Choose how to pay</p>
        @include('water.demo.option')
        <details class="fc-manual-option" @if($errors->any()) open @endif>
            <summary><span><strong>Manual QR Payment</strong><small>Pay the Treasurer and submit your proof.</small></span><span aria-hidden="true">＋</span></summary>
            <div class="fc-manual-content">
            @if($member->status === 'rejected')<p class="water-alert">Please correct your proof: {{ $member->latestSubmission?->rejection_reason }}</p>@endif
            @if($qrAvailable)
                <div class="fc-qr-account"><img class="water-qr" loading="lazy" src="{{ route('water.qr', $collection) }}" alt="Treasurer receiving account QR">
                <div><h3>{{ $collection->account->account_name }}</h3><p>{{ $collection->account->label }} · {{ $collection->account->masked_account_number }}</p><a class="button" href="{{ route('water.qr', [$collection, 'download' => 1]) }}">Save QR</a></div></div>
                <p class="water-prewrap">{{ $collection->account->instructions }}</p>
                <p>Pay the exact amount to this account, then submit your reference and proof below. The Treasurer will verify your payment.</p>
            @else
                <p class="water-alert">QR unavailable. Contact the Treasurer for confirmed account instructions before paying.</p>
            @endif
            @include('water.partials.payment-form')
            </div>
        </details>
    @endif
    </section></div>
</div>
@endsection
