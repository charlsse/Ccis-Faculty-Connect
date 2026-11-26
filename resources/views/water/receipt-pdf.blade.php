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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Receipt | FacultyConnect</title><style>{!! file_get_contents(public_path('css/payment-receipt.css')) !!} body{background:white;font-size:11px}.fc-receipt{margin:0;padding:24px 10px;box-shadow:none;max-width:none}</style></head><body>@include('water.partials.receipt-document', ['pdfView'=>true])</body></html>