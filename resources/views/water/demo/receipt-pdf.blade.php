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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Receipt | FacultyConnect</title><style>{!! file_get_contents(public_path('css/payment-receipt.css')) !!} body{background:white;font-size:11px}.fc-receipt{margin:0;padding:24px 10px;box-shadow:none;max-width:none}</style></head><body>@include('water.partials.receipt-document', ['pdfView'=>true])</body></html>