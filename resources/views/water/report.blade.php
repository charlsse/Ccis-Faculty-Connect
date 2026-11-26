@extends('layouts.dashboard')
@section('title', 'Water collection report')
@section('content')<div class="water-module">
@include('water.partials.header', ['heading' => 'Collection report'])
<h2>{{ $collection->title }} · {{ $collection->period }}</h2>
@include('water.partials.summary')
<div class="water-actions"><a class="button button-primary" href="{{ route('water.report', [$collection, 'format'=>'xlsx']) }}">Download Excel report</a><a class="button" href="{{ route('water.report', [$collection, 'download'=>1]) }}">Download CSV data</a></div>
<p>The Excel report includes a summary and a landscape transaction sheet, with repeating print headers and page numbers. In Excel, choose Print Entire Workbook to print both sheets.</p>
<p>Expected includes waivers; outstanding excludes paid and waived obligations. Method, reference and payment date may describe an unverified submission; only Paid amounts count as collected. Times use {{ config('app.timezone') }}.</p>
<div class="water-table-scroll" role="region" tabindex="0" aria-label="Collection report, scroll horizontally"><table class="water-table"><thead><tr><th>Faculty</th><th>Assigned</th><th>Paid</th><th>Method / reference</th><th>Status</th><th>Payment date</th><th>Verification / officer</th><th>Receipt</th></tr></thead><tbody>@forelse($members as $member)@php($r = $member->receipt)<tr><td>{{ $member->user->full_name }}</td><td>₱{{ $member->assigned_amount }}</td><td>₱{{ $r?->amount ?? '0.00' }}</td><td>{{ $r?->payment_method ?? $member->latestSubmission?->payment_method ?? '—' }}<br>{{ $r?->payment_reference ?? $member->latestSubmission?->reference_number }}</td><td>{{ $member->status_label }}</td><td>{{ $r?->payment_date ?? $member->latestSubmission?->paid_at }}</td><td>{{ $r?->verified_at }}<br>{{ $r?->snapshot['officer_name'] ?? '' }}</td><td>@if($r)<a href="{{ route('water.receipt', $r) }}">{{ $r->receipt_number }}</a>@else—@endif</td></tr>@empty<tr><td colspan="8">No assigned members.</td></tr>@endforelse</tbody></table></div>{{ $members->links() }}
</div>@endsection
