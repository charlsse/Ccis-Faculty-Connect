@extends('layouts.dashboard')

@section('title', 'Content Manager')

@php
    $slides = old('carousel_slides', $content['carousel_slides']);
    $portal = old('portal_preview', $content['portal_preview']);
    $about = old('about', $content['about']);
    $features = old('features', $content['features']);
    $benefits = old('benefits', $content['benefits']);
    $cta = old('cta', $content['cta']);
    $branding = \App\Models\SiteContent::branding();
@endphp

@section('content')
    <div data-content-errors='@json(array_keys($errors->messages()))'></div>
    <section class="dashboard-hero content-hero">
        <div>
            <p class="eyebrow">Admin Content Manager</p>
            <h1>Manage landing page content</h1>
            <span>Edit public-facing text, carousel slides, image placements, feature cards, and CTA copy without changing Blade files.</span>
        </div>
        <a class="button button-secondary" href="{{ route('landing') }}" target="_blank" rel="noopener">View Landing Page</a>
    </section>

    @if (session('status'))
        <div class="dashboard-alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="dashboard-alert error" role="alert">
            Please review these fields and try saving again.<ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <nav class="content-categories fc-liquid-glass fc-glass-subtle" data-content-categories aria-label="Content categories">
        <div role="tablist" aria-label="Website sections">
            @foreach(['branding' => 'Branding', 'hero' => 'Hero slides', 'preview' => 'Portal preview', 'about' => 'About', 'features' => 'Features', 'benefits' => 'Benefits', 'cta' => 'Call to action'] as $key => $label)
            <button type="button" role="tab" id="category-{{ $key }}" aria-controls="editor-{{ $key }}" data-category="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <label class="mobile-category">Editing section<select data-category-select aria-label="Editing section">
            @foreach(['branding' => 'Branding', 'hero' => 'Hero slides', 'preview' => 'Portal preview', 'about' => 'About', 'features' => 'Features', 'benefits' => 'Benefits', 'cta' => 'Call to action'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select></label>
        <p>Switch sections freely. Your unsaved edits stay here. Website icon saves separately; Save Content saves all landing sections.</p>
    </nav>
    <form data-editor-panel="branding" aria-labelledby="category-branding" class="content-editor-section fc-branding-editor" method="POST" action="{{ route('admin.content.branding') }}" enctype="multipart/form-data" id="editor-branding">
        @csrf
        <div class="editor-section-heading"><p class="eyebrow">Site branding</p><h2>Website icon</h2><p>The icon shown in browser tabs and saved shortcuts. Your main site logo stays the same.</p></div>
        <div class="fc-branding-grid">
            <div class="fc-icon-sample"><img data-icon-preview src="{{ route('site.icon', ['v' => $branding['version']]) }}" alt="Current FacultyConnect website icon" width="72" height="72"><span data-icon-preview-label>Current icon</span></div>
            <div class="fc-icon-fields">
                <label for="site-icon">Upload a replacement icon</label>
                <input id="site-icon" type="file" name="site_icon" accept="image/png,.png" aria-describedby="site-icon-help{{ $errors->has('site_icon') ? ' site-icon-error' : '' }}" @error('site_icon') aria-invalid="true" @enderror>
                <p id="site-icon-help">Square PNG, 180–512 pixels, up to 512 KB. A new upload takes precedence over restoring the default.</p>
                @error('site_icon')<span id="site-icon-error" class="field-error" role="alert">{{ $message }}</span>@enderror
                <label class="checkbox-row"><input type="checkbox" name="restore_icon" value="1"> Restore the default CCIS icon</label>
                <p data-icon-feedback role="status"></p>
                <button class="button button-primary" type="submit">Save Website Icon</button>
            </div>
        </div>
    </form>

    <form class="content-manager-form" method="POST" action="{{ route('admin.content.update') }}" enctype="multipart/form-data">
        @csrf

        <section class="content-editor-section" id="editor-hero" data-editor-panel="hero" aria-labelledby="category-hero">
            <div class="editor-section-heading">
                <p class="eyebrow">Hero Carousel</p>
                <h2>Top landing slides</h2>
            </div>

            <div class="editor-grid">
                @foreach ($slides as $slide)
                    @php
                        $slideIndex = $loop->index;
                        $imagePath = $slide['image_path'] ?? $slide['existing_image_path'] ?? null;
                    @endphp
                    <article class="editor-card">
                        <div class="editor-card-head">
                            <strong>Slide {{ $loop->iteration }}</strong>
                            @if ($imagePath)
                                <span class="status-pill paid">Has image</span>
                            @endif
                        </div>
                        <input type="hidden" name="carousel_slides[{{ $slideIndex }}][existing_image_path]" value="{{ $imagePath }}">

                        <label>
                            Eyebrow
                            <input type="text" name="carousel_slides[{{ $slideIndex }}][eyebrow]" value="{{ $slide['eyebrow'] ?? '' }}" required>
                            @error('carousel_slides.'.$slideIndex.'.eyebrow') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            Title
                            <input type="text" name="carousel_slides[{{ $slideIndex }}][title]" value="{{ $slide['title'] ?? '' }}" required>
                            @error('carousel_slides.'.$slideIndex.'.title') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            Description
                            <textarea name="carousel_slides[{{ $slideIndex }}][body]" rows="4" required>{{ $slide['body'] ?? '' }}</textarea>
                            @error('carousel_slides.'.$slideIndex.'.body') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label>Interface preview
                            <select name="carousel_slides[{{ $slideIndex }}][visual]">
                                @foreach(['workspace' => 'Faculty workspace', 'contributions' => 'Water contributions', 'security' => 'Account security'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($slide['visual'] ?? ['workspace','contributions','security'][$slideIndex % 3]) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('carousel_slides.'.$slideIndex.'.visual') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            Slide image
                            <input type="file" name="carousel_images[{{ $slideIndex }}]" accept="image/*">
                            @error('carousel_images.'.$slideIndex) <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        @if ($imagePath)
                            <figure class="editor-image-preview">
                                <img src="{{ asset($imagePath) }}" alt="">
                            </figure>
                            <label class="checkbox-row">
                                <input type="checkbox" name="carousel_slides[{{ $slideIndex }}][remove_image]" value="1">
                                Remove current image
                            </label>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="content-editor-section" id="editor-preview" data-editor-panel="preview" aria-labelledby="category-preview">
            <div class="editor-section-heading">
                <p class="eyebrow">Portal Preview</p>
                <h2>Right-side hero card</h2>
            </div>
            <div class="editor-grid two">
                @foreach ($portal['metrics'] as $metric)
                    <article class="editor-card">
                        <strong>Metric {{ $loop->iteration }}</strong>
                        <label>
                            Title
                            <input type="text" name="portal_preview[metrics][{{ $loop->index }}][title]" value="{{ $metric['title'] ?? '' }}" required>
                        </label>
                        <label>
                            Supporting text
                            <input type="text" name="portal_preview[metrics][{{ $loop->index }}][body]" value="{{ $metric['body'] ?? '' }}" required>
                        </label>
                    </article>
                @endforeach
                <article class="editor-card">
                    <strong>Progress bars</strong>
                    @foreach ($portal['bars'] as $width)
                        <label>
                            Bar {{ $loop->iteration }} width
                            <input type="number" name="portal_preview[bars][{{ $loop->index }}]" value="{{ $width }}" min="20" max="100" required>
                        </label>
                    @endforeach
                </article>
            </div>
        </section>

        <section class="content-editor-section" id="editor-about" data-editor-panel="about" aria-labelledby="category-about">
            <div class="editor-section-heading">
                <p class="eyebrow">About Section</p>
                <h2>Introductory copy and badges</h2>
            </div>
            <div class="editor-card">
                <label>
                    Eyebrow
                    <input type="text" name="about[eyebrow]" value="{{ $about['eyebrow'] ?? '' }}" required>
                </label>
                <label>
                    Title
                    <input type="text" name="about[title]" value="{{ $about['title'] ?? '' }}" required>
                </label>
                <label>
                    Body
                    <textarea name="about[body]" rows="5" required>{{ $about['body'] ?? '' }}</textarea>
                </label>
                <div class="editor-grid three compact-grid">
                    @foreach ($about['strip'] as $item)
                        <label>
                            Badge {{ $loop->iteration }}
                            <input type="text" name="about[strip][{{ $loop->index }}]" value="{{ $item }}" required>
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="content-editor-section" id="editor-features" data-editor-panel="features" aria-labelledby="category-features">
            <div class="editor-section-heading">
                <p class="eyebrow">Feature Cards</p>
                <h2>Module preview cards</h2>
            </div>
            <div class="editor-grid three">
                @foreach ($features as $feature)
                    <article class="editor-card">
                        <strong>Feature {{ $loop->iteration }}</strong>
                        <label>
                            Icon text
                            <input type="text" name="features[{{ $loop->index }}][icon]" value="{{ $feature['icon'] ?? '' }}" maxlength="4" required>
                        </label>
                        <label>
                            Title
                            <input type="text" name="features[{{ $loop->index }}][title]" value="{{ $feature['title'] ?? '' }}" required>
                        </label>
                        <label>
                            Status
                            <input type="text" name="features[{{ $loop->index }}][status]" value="{{ $feature['status'] ?? '' }}" required>
                        </label>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="content-editor-section" id="editor-benefits" data-editor-panel="benefits" aria-labelledby="category-benefits">
            <div class="editor-section-heading">
                <p class="eyebrow">Benefits</p>
                <h2>Landing benefit cards</h2>
            </div>
            <div class="editor-grid two">
                @foreach ($benefits as $benefit)
                    <article class="editor-card">
                        <strong>Benefit {{ $loop->iteration }}</strong>
                        <label>
                            Title
                            <input type="text" name="benefits[{{ $loop->index }}][title]" value="{{ $benefit['title'] ?? '' }}" required>
                        </label>
                        <label>
                            Body
                            <textarea name="benefits[{{ $loop->index }}][body]" rows="3" required>{{ $benefit['body'] ?? '' }}</textarea>
                        </label>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="content-editor-section" id="editor-cta" data-editor-panel="cta" aria-labelledby="category-cta">
            <div class="editor-section-heading">
                <p class="eyebrow">Call to Action</p>
                <h2>Bottom CTA block</h2>
            </div>
            <div class="editor-card">
                <label>
                    CTA title
                    <input type="text" name="cta[title]" value="{{ $cta['title'] ?? '' }}" required>
                </label>
                <div class="editor-grid compact-grid">
                    <label>
                        Login button label
                        <input type="text" name="cta[login_label]" value="{{ $cta['login_label'] ?? '' }}" required>
                    </label>
                </div>
            </div>
        </section>

        <div class="content-editor-actions">
            <a class="button button-secondary" href="{{ route('dashboard') }}">Cancel</a>
            <button class="button button-primary" type="submit">Save Content</button>
        </div>
    </form>
@endsection

