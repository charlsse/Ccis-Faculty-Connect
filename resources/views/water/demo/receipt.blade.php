@php

$receiptData = [
    'number' => $attempt->receipt_number, 'simulation' => true, 'amount' => $attempt->amount_minor / 100,
    'title' => $attempt->contribution_title, 'period' => $attempt->billing_period, 'payer' => $faculty->full_name,
    'method' => 'Online Payment', 'reference' => $attempt->display_reference,
    'paymentDate' => $attempt->paid_at?->timezone(config('app.timezone'))->format('F j, Y · H:i'),
    'verifiedDate' => $attempt->confirmed_at?->timezone(config('app.timezone'))->format('F j, Y · H:i'),
    'officer' => 'Verified online payment', 'collectionReference' => 'Contribution #'.$attempt->water_member_id,
];


@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Payment Receipt | FacultyConnect</title><link rel="stylesheet" href="{{ asset('css/payment-receipt.css') }}?v={{ filemtime(public_path('css/payment-receipt.css')) }}"></head><body><nav class="fc-receipt-actions" aria-label="Receipt actions"><a href="{{ route('water.demo.show', $attempt) }}">Back to payment</a><a href="{{ route('water.demo.receipt', [$attempt, 'download'=>1]) }}">Download PDF</a><button onclick="window.print()">Print receipt</button></nav>@include('water.partials.receipt-document')</body></html>