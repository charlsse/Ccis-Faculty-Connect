<article class="fc-receipt">
<header class="fc-receipt-header"><img src="{{ ($pdfView ?? false) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/ccis-receipt-logo.jpg'))) : asset('images/ccis-logo.png') }}" alt="CCIS Faculty Club" width="56" height="56"><div class="fc-receipt-brand"><strong>FacultyConnect</strong><span>CCIS Faculty Club</span><small>University of Makati</small></div></header>
<div class="fc-receipt-title"><p class="fc-receipt-kicker">PAYMENT RECEIPT</p><h1>{{ $receiptData['title'] }}</h1><p>{{ $receiptData['period'] }}</p><span class="fc-receipt-status">Completed · Verified</span></div>
<div class="fc-receipt-total"><span>{{ $receiptData['simulation'] ? 'Simulated payment amount' : 'Amount paid' }}</span><strong>PHP {{ number_format($receiptData['amount'], 2) }}</strong></div>
@if($receiptData['simulation'])<p class="fc-receipt-notice">Simulation — No Funds Collected. This receipt does not settle actual contribution dues.</p>@endif
<table class="fc-receipt-details"><tbody>
@foreach(['Receipt number'=>$receiptData['number'], 'Payer'=>$receiptData['payer'], 'Payment method'=>$receiptData['method'], 'Transaction reference'=>$receiptData['reference'], 'Payment date'=>$receiptData['paymentDate'], 'Verified at'=>$receiptData['verifiedDate'], 'Verification'=>$receiptData['officer'], 'Collection reference'=>$receiptData['collectionReference']] as $label=>$value)
<tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
@endforeach
</tbody></table>
<footer class="fc-receipt-footer"><strong>Thank you for supporting the CCIS Faculty Club.</strong><p>System-generated receipt. Retain the receipt number for your records.<br>All times: {{ config('app.timezone') }}.</p></footer>
</article>
