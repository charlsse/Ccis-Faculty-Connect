@php

$receiptData = [
    'number' => $receipt->receipt_number, 'simulation' => in_array($receipt->payment_method, ['paymongo_test','xendit_test'], true),
    'amount' => $receipt->amount, 'title' => $receipt->snapshot['collection_title'], 'period' => $receipt->snapshot['period'],
    'payer' => $receipt->snapshot['faculty_name'],
    'method' => in_array($receipt->payment_method, ['paymongo_test','xendit_test'], true) ? 'Online Payment' : ($receipt->payment_method === 'cash' ? 'Cash' : 'Manual QR'),
    'reference' => $receipt->payment_reference ?? 'Not applicable',
    'paymentDate' => $receipt->payment_date->timezone(config('app.timezone'))->format('F j, Y · H:i'),
    'verifiedDate' => $receipt->verified_at->timezone(config('app.timezone'))->format('F j, Y · H:i'),
    'officer' => $receipt->snapshot['officer_name'], 'collectionReference' => 'Contribution #'.$receipt->water_member_id,
];


@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Payment Receipt | FacultyConnect</title><link rel="stylesheet" href="{{ asset('css/payment-receipt.css') }}?v={{ filemtime(public_path('css/payment-receipt.css')) }}"></head><body><nav class="fc-receipt-actions" aria-label="Receipt actions"><a href="{{ route('water.show', $receipt->member->water_collection_id) }}">Back to collection</a><a href="{{ route('water.receipt', [$receipt, 'download'=>1]) }}">Download PDF</a><button onclick="window.print()">Print receipt</button></nav>@include('water.partials.receipt-document')</body></html>