@extends('layouts.dashboard')

@section('title', $announcement->title)

@section('content')
    <section class="announcement-detail">
        @if ($announcement->image_path)
            <figure class="announcement-detail-image">
                <img src="{{ asset($announcement->image_path) }}" alt="">
            </figure>
        @endif

        <div class="announcement-detail-body">
            <div class="announcement-meta">
                @if ($announcement->is_pinned)
                    <span class="status-pill pinned">Pinned</span>
                @endif
                <span class="status-pill {{ $announcement->priority }}">{{ $announcement->priority_label }}</span>
                <span>{{ $announcement->category }}</span>
                <span>{{ $announcement->audience }}</span>
            </div>

            <h1>{{ $announcement->title }}</h1>
            <div class="announcement-byline">
                Posted {{ optional($announcement->published_at)->format('M j, Y g:i A') ?? 'as draft' }}
                @if ($announcement->author)
                    by {{ $announcement->author->full_name }}
                @endif
            </div>
            <p>{{ $announcement->body }}</p>

            <div class="form-actions split">
                <a class="button button-secondary" href="{{ route('announcements.index') }}">Back to Announcements</a>
                @if (auth()->user()->hasPermission('announcements.manage'))
                    <form method="POST" action="{{ route('announcements.destroy', $announcement) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button danger-button" type="submit">Delete</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if (auth()->user()->hasPermission('announcements.manage'))
        <section class="content-editor-section">
            <div class="editor-section-heading">
                <p class="eyebrow">Faculty President Tools</p>
                <h2>Edit announcement</h2>
            </div>

            @if (session('status'))
                <div class="dashboard-alert success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="dashboard-alert error" role="alert">Please review the highlighted fields and try again.</div>
            @endif

            <form class="announcement-form" method="POST" action="{{ route('announcements.update', $announcement) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <div class="form-columns">
                    <label>
                        Title
                        <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required>
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Category
                        <input type="text" name="category" value="{{ old('category', $announcement->category) }}" required>
                        @error('category') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Audience
                        <select name="audience" required>
                            @unless (in_array(old('audience', $announcement->audience), \App\Models\Announcement::audiences(), true))
                                <option value="" selected disabled>Choose an audience (previous label: {{ $announcement->audience }})</option>
                            @endunless
                            @foreach (\App\Models\Announcement::audiences() as $audience)
                                <option value="{{ $audience }}" @selected(old('audience', $announcement->audience) === $audience)>{{ $audience }}</option>
                            @endforeach
                        </select>
                        <small>All Faculty / All Members includes all portal roles. Other audiences match the selected role.</small>
                        @error('audience') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Priority
                        <select name="priority" required>
                            @foreach (['normal' => 'Normal', 'important' => 'Important', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $announcement->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Publish date
                        <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($announcement->published_at)->format('Y-m-d\TH:i')) }}">
                        @error('published_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Expiration date
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($announcement->expires_at)->format('Y-m-d\TH:i')) }}">
                        @error('expires_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label>
                    Announcement body
                    <textarea name="body" rows="6" required>{{ old('body', $announcement->body) }}</textarea>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="form-columns two">
                    <label>
                        Replace image
                        <input type="file" name="image" accept="image/*">
                        @error('image') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <div class="stacked-checks">
                        <label class="checkbox-row form-checkbox">
                            <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))>
                            Pin this announcement
                        </label>
                        @if ($announcement->image_path)
                            <label class="checkbox-row form-checkbox">
                                <input type="checkbox" name="remove_image" value="1">
                                Remove current image
                            </label>
                        @endif
                    </div>
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Save Announcement</button>
                </div>
            </form>
        </section>
    @endif
@endsection
