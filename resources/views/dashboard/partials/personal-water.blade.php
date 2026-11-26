<section class="water-module" aria-label="My contributions">
    <div class="section-heading"><h2>My Contributions</h2><a href="{{ route('water.index') }}">Payment history &amp; all periods</a></div>
    @forelse($personalWater as $obligation)
        @php($online = \App\Services\Payments\WaterGatewayService::active($obligation))
        <article class="water-card">
            <h3>{{ $obligation->collection->title }}</h3>
            <p>{{ $obligation->collection->period }} <span class="water-badge" data-status="{{ $obligation->status }}">{{ $obligation->status_label }}</span></p>
            <p>Assigned: &#8369;{{ number_format($obligation->assigned_amount, 2) }} &middot; Verified: &#8369;{{ number_format($obligation->status === 'paid' ? $obligation->assigned_amount : 0, 2) }} &middot; Remaining: &#8369;{{ number_format(in_array($obligation->status, ['paid', 'waived']) ? 0 : $obligation->assigned_amount, 2) }}</p>
            @if($online)
                <p>An online payment is unresolved. Check its status before paying again.</p>
                <a class="button button-primary" href="{{ route('water.online.show', $online) }}">Check online payment</a>
            @elseif($obligation->status === 'pending' && $obligation->latestSubmission)
                <p>Awaiting Treasurer verification. Please do not submit the same payment again.</p>
                <a class="button button-primary" href="{{ route('water.submission', $obligation->latestSubmission) }}">View pending submission</a>
            @elseif(in_array($obligation->status, ['unpaid', 'rejected']) && $obligation->collection->status === 'published')
                <a class="button button-primary" href="{{ route('water.checkout', $obligation) }}">Pay Now</a>
                <p>Pay externally, then submit payment proof on the collection page.</p>
            @else
                <p>{{ in_array($obligation->status, ['paid', 'waived']) ? 'No outstanding contribution for this period.' : 'This collection is not accepting submissions. Contact the Treasurer for assistance.' }}</p>
                <a class="button button-secondary" href="{{ route('water.show', $obligation->collection) }}">View payment history</a>
            @endif
        </article>
    @empty
        <p>No water contributions are currently assigned to you.</p>
    @endforelse
</section>
