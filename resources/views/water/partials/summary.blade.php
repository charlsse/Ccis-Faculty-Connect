<section class="water-grid" aria-label="Water payment summary">
    @can('manage', App\Models\WaterCollection::class)
        <article class="water-card"><span>Total expected</span><strong>₱{{ number_format($summary['expected'], 2) }}</strong></article>
        <article class="water-card"><span>Total verified</span><strong>₱{{ number_format($summary['collected'], 2) }}</strong></article>
    @endcan
    <article class="water-card"><span>{{ auth()->user()->hasPermission('payments.view') ? 'Outstanding balance' : 'Current amount due' }}</span><strong>₱{{ number_format($summary['outstanding'], 2) }}</strong></article>
    <article class="water-card"><span>Pending verification</span><strong>{{ $summary['pending'] }}</strong><small>₱{{ number_format($summary['pending_amount'], 2) }} awaiting verification; included in outstanding balance</small></article>
    @can('manage', App\Models\WaterCollection::class)
        <article class="water-card"><span>Paid / unpaid or overdue</span><strong>{{ $summary['paid'] }} / {{ $summary['unpaid'] }}</strong><small>{{ $summary['rejected'] }} rejected · ₱{{ number_format($summary['waived'], 2) }} waived</small></article>
    @else
        <article class="water-card"><span>Next outstanding due date</span><strong>{{ $summary['due'] ? \Illuminate\Support\Carbon::parse($summary['due'])->format('M j, Y') : 'None' }}</strong></article>
        <article class="water-card"><span>Latest verified payment date</span><strong>{{ $summary['latest'] ? \Illuminate\Support\Carbon::parse($summary['latest'])->format('M j, Y') : 'No payments yet' }}</strong></article>
    @endcan
</section>
