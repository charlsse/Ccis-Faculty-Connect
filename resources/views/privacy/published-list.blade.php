<section class="privacy-published" aria-labelledby="published-heading">
    <div class="privacy-section-heading"><div><p class="eyebrow">For users</p><h2 id="published-heading">Published policies</h2></div><span class="status-pill">{{ count($published) }} published</span></div>
    @forelse($published as $slug => $policy)
        <a class="privacy-document-link" href="{{ route('privacy.published', $slug) }}"><span><strong>{{ $policy['title'] }}</strong><small>Version {{ $policy['version'] }} · Effective {{ \Illuminate\Support\Carbon::parse($policy['approval']['effective_at'])->toDateString() }}</small></span><span aria-hidden="true">&rarr;</span></a>
    @empty
        <div class="privacy-empty"><strong>Awaiting policy approval</strong><p>FacultyConnect's draft Privacy Notice and Terms of Use require review and approval. No approved user-facing policies have been published here. This status message is not a privacy policy or a request for consent.</p></div>
    @endforelse
</section>
