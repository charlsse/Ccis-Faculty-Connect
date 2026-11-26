<section class="fc-bulletin" data-fc-carousel data-interval="7000" role="region" aria-roledescription="carousel" aria-label="Latest announcements">
    <div class="fc-bulletin-heading"><p class="eyebrow">THE FACULTY BULLETIN</p><a href="{{ route('announcements.index') }}">All announcements <span aria-hidden="true">↗</span></a></div>
    <div class="fc-slide-track" aria-live="off">
        @forelse($announcements as $announcement)
            <article class="fc-bulletin-slide" data-fc-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $announcements->count() }}" @if(!$loop->first) hidden @endif>
                <div class="fc-bulletin-meta"><span>{{ $announcement->category }}</span><time datetime="{{ $announcement->published_at->toDateString() }}">{{ $announcement->published_at->format('M j, Y') }}</time>@if($announcement->priority !== 'normal')<span class="fc-bulletin-priority">{{ $announcement->priority_label }}</span>@endif</div>
                <h2>{{ $announcement->title }}</h2>
                <p>{{ \Illuminate\Support\Str::limit($announcement->body, 220) }}</p>
                <a class="fc-bulletin-link" href="{{ route('announcements.show', $announcement) }}">View announcement <span aria-hidden="true">↗</span></a>
            </article>
        @empty
            <div class="fc-bulletin-empty"><span aria-hidden="true">◎</span><div><h2>No new announcements.</h2><p>You're all caught up. Published faculty updates will appear here.</p></div></div>
        @endforelse
    </div>
    @if($announcements->count() > 1)
        <div class="fc-bulletin-controls" data-fc-controls hidden>
            <div class="fc-carousel-pages">@foreach($announcements as $announcement)<button type="button" data-fc-go="{{ $loop->index }}" aria-label="Show announcement {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</button>@endforeach</div>
            <button type="button" data-fc-prev aria-label="Previous announcement">←</button><button type="button" data-fc-next aria-label="Next announcement">→</button><button type="button" data-fc-pause aria-label="Pause announcements">Ⅱ</button>
            <span class="visually-hidden" data-fc-status aria-live="polite" aria-atomic="true"></span>
        </div>
    @endif
</section>
