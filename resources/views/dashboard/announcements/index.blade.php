@extends('layouts.dashboard')

@section('title', 'Announcements')

@section('content')
    <section class="dashboard-hero content-hero">
        <div>
            <p class="eyebrow">CCIS Faculty Club</p>
            <h1>Announcements</h1>
            <span>Official updates, meetings, deadlines, and faculty club advisories.</span>
        </div>
        @if (auth()->user()->hasPermission('announcements.manage'))
            <a class="button button-secondary" href="#new-announcement">New Announcement</a>
        @endif
    </section>

    @if (session('status'))
        <div class="dashboard-alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="dashboard-alert error" role="alert">Please review the highlighted fields and try again.</div>
    @endif

    @if (auth()->user()->hasPermission('announcements.manage'))
        <section class="content-editor-section" id="new-announcement">
            <div class="editor-section-heading">
                <p class="eyebrow">Faculty President Tools</p>
                <h2>Create announcement</h2>
            </div>

            <form class="announcement-form" method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-columns">
                    <label>
                        Title
                        <input type="text" name="title" value="{{ old('title') }}" required>
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Category
                        <input type="text" name="category" value="{{ old('category', 'General') }}" required>
                        @error('category') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Audience
                        <select name="audience" required>@foreach(\App\Models\Announcement::audiences() as $audience)<option value="{{ $audience }}" @selected(old('audience', 'All Faculty') === $audience)>{{ $audience }}</option>@endforeach</select><small>All Faculty / All Members includes all portal roles. Other audiences match the selected role.</small>
                        @error('audience') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Priority
                        <select name="priority" required>
                            @foreach (['normal' => 'Normal', 'important' => 'Important', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Publish date
                        <input type="datetime-local" name="published_at" value="{{ old('published_at', now()->format('Y-m-d\TH:i')) }}">
                        @error('published_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Expiration date
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
                        @error('expires_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label>
                    Announcement body
                    <textarea name="body" rows="6" required>{{ old('body') }}</textarea>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="form-columns two">
                    <label>
                        Optional image
                        <input type="file" name="image" accept="image/*">
                        @error('image') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="checkbox-row form-checkbox">
                        <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned'))>
                        Pin this announcement
                    </label>
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Publish Announcement</button>
                </div>
            </form>
        </section>
    @endif

    <section class="announcement-board">
        @forelse ($announcements as $announcement)
            <article class="announcement-card {{ $announcement->is_pinned ? 'pinned' : '' }}">
                @if ($announcement->image_path)
                    <a class="announcement-image" href="{{ route('announcements.show', $announcement) }}">
                        <img src="{{ asset($announcement->image_path) }}" alt="">
                    </a>
                @endif
                <div class="announcement-card-body">
                    <div class="announcement-meta">
                        @if ($announcement->is_pinned)
                            <span class="status-pill pinned">Pinned</span>
                        @endif
                        <span class="status-pill {{ $announcement->priority }}">{{ $announcement->priority_label }}</span>
                        <span>{{ $announcement->category }}</span>
                    </div>
                    <h2><a href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h2>
                    <p>{{ \Illuminate\Support\Str::limit($announcement->body, 190) }}</p>
                    <div class="announcement-footer">
                        <span>{{ optional($announcement->published_at)->format('M j, Y g:i A') ?? 'Draft' }}</span>
                        <a href="{{ route('announcements.show', $announcement) }}">Open</a>
                    </div>
                </div>
            </article>
        @empty
            <section class="empty-state">
                <p class="eyebrow">No Updates</p>
                <h2>No announcements have been published yet.</h2>
                <span>Faculty Club updates will appear here once posted by the Faculty President.</span>
            </section>
        @endforelse
    </section>

    @if ($announcements->hasPages())
        <nav class="simple-pagination" aria-label="Announcements pagination">
            @if ($announcements->onFirstPage())
                <span>Previous</span>
            @else
                <a href="{{ $announcements->previousPageUrl() }}">Previous</a>
            @endif

            <strong>Page {{ $announcements->currentPage() }} of {{ $announcements->lastPage() }}</strong>

            @if ($announcements->hasMorePages())
                <a href="{{ $announcements->nextPageUrl() }}">Next</a>
            @else
                <span>Next</span>
            @endif
        </nav>
    @endif
@endsection
