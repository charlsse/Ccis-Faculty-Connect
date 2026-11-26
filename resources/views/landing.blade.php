@extends('layouts.app')
@section('title', 'FacultyConnect — CCIS Faculty Club')
@section('bodyClass', 'fc-public')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush
@section('content')
<noscript><style>.fc-public .nav-links{display:flex!important;position:static!important;visibility:visible!important;opacity:1!important;transform:none!important;flex-wrap:wrap;width:100%;max-height:none}.fc-public .nav-toggle,.fc-slide-controls,.fc-role-buttons{display:none!important}.fc-public .site-nav{flex-wrap:wrap;padding-block:12px}.fc-slide[hidden],[data-role-panel][hidden]{display:block!important;visibility:visible!important}.fc-slide{margin-bottom:24px}.fc-public .site-header{position:relative;margin-bottom:0}.fc-public .fc-cinematic{padding-top:20px}</style></noscript>
<a class="skip-link" href="#main-content">Skip to content</a>
<span class="fc-scroll-sentinel" data-nav-sentinel aria-hidden="true"></span>
<button class="fc-menu-backdrop" data-menu-backdrop type="button" aria-label="Close navigation" tabindex="-1" hidden></button>
<header class="site-header" data-public-header><nav class="site-nav" aria-label="Primary navigation">
    <div class="fc-nav-material fc-liquid-glass fc-glass-clear" aria-hidden="true"></div>
    @include('partials.brand')
    <div class="fc-public-appearance">@include('partials.theme-toggle')</div>
    <button class="nav-toggle" type="button" data-nav-toggle data-glass-menu-toggle aria-expanded="false" aria-controls="primaryNav" aria-label="Toggle navigation"><span></span><span></span><span></span></button>
    <div class="nav-links fc-liquid-glass fc-glass-regular" id="primaryNav"><a href="#home">Home</a><a href="#about">About</a><a href="#features">Features</a>
    @auth <a class="nav-login" href="{{ route('dashboard') }}">Dashboard @include('partials.ui-arrow')</a>
    @else <a class="nav-login" href="{{ route('login') }}">Login @include('partials.ui-arrow')</a> @endauth</div>
</nav></header>
<main id="main-content">
<section class="fc-cinematic" id="home" aria-labelledby="hero-title">
<div class="fc-hero-backdrop" aria-hidden="true"><div class="fc-hero-scene fc-hero-scene--light"></div><div class="fc-hero-scene fc-hero-scene--dark"></div></div>
<div class="fc-container">
<div class="institution-line"><span>UNIVERSITY OF MAKATI</span><span>COLLEGE OF COMPUTING &amp; INFORMATION SCIENCES</span></div>
<div class="fc-cinematic-grid"><div class="fc-cinematic-copy"><p class="eyebrow"><span class="live-dot"></span> THE CCIS FACULTY COMMUNITY</p>
<h1 id="hero-title">Connecting faculty.<br><em>Empowering</em><br>collaboration.</h1>
<p class="fc-cinematic-lead">A shared space for the people behind CCIS. Stay informed, manage club contributions, and keep your faculty community connected.</p>
<div class="fc-hero-actions"><a class="button button-primary" href="#about">Explore FacultyConnect @include('partials.ui-arrow', ['direction' => 'down'])</a><a class="button fc-signin fc-liquid-glass fc-glass-clear" href="{{ route('login') }}">Faculty Sign In @include('partials.ui-arrow')</a></div><p class="fc-access-note">Your faculty club. One secure connection.</p></div>
<figure class="fc-emblem-scene" data-emblem-scene><div class="fc-emblem-orbit" aria-hidden="true"></div>
<div class="fc-microdots fc-microdots--halo" data-microdots="halo" aria-hidden="true"><canvas aria-hidden="true"></canvas></div><div class="fc-emblem-float"><div class="fc-emblem" data-emblem>
@for($depth = 1; $depth <= 8; $depth++)<span class="fc-emblem-edge" style="--depth:{{ $depth }}" aria-hidden="true"></span>@endfor
<img src="{{ asset('images/ccis-logo.png') }}" width="1100" height="1100" alt="Official College of Computing and Information Sciences, University of Makati seal" fetchpriority="high"></div></div>
<figcaption>ROOTED IN CCIS. CONNECTED BY PURPOSE.<span>University of Makati</span></figcaption></figure></div>
<div class="fc-hero-bottom"><span>Faculty coordination <b aria-hidden="true">/</b> Transparent contributions <b aria-hidden="true">/</b> Protected access</span><a href="#about">Discover your community @include('partials.ui-arrow', ['direction' => 'down'])</a></div></div></section>
{{-- One accessible sequence; duplicated tracks are visual only. --}}
<div class="fc-marquee fc-container" data-marquee>
    <p class="fc-marquee-static" data-marquee-label><span>FACULTYCONNECT</span> <span>CONNECT</span> <span>COLLABORATE</span> <span>EMPOWER</span></p>
    <div class="fc-marquee-viewport" aria-hidden="true">
        <div class="fc-marquee-track" data-marquee-track>
            @for($group = 0; $group < 2; $group++)
                <div class="fc-marquee-group" data-marquee-group>
                    @for($sequence = 0; $sequence < 2; $sequence++)
                        @foreach(['FACULTYCONNECT', 'CONNECT', 'COLLABORATE', 'EMPOWER'] as $phrase)
                            <span class="fc-marquee-word {{ $loop->even ? 'fc-marquee-gold' : '' }}">{{ $phrase }}</span><i class="fc-marquee-divider"></i>
                        @endforeach
                    @endfor
                </div>
            @endfor
        </div>
    </div>
</div>
<section class="fc-hero" id="highlights" data-fc-carousel data-interval="6500" role="region" aria-roledescription="carousel" aria-label="FacultyConnect highlights">
    <div class="fc-container fc-highlights-heading"><p class="eyebrow">OUR PEOPLE. OUR COMMUNITY.</p><span>From the CCIS Faculty Club</span></div>
    <div class="fc-banner-track" data-reveal="scale">
        @foreach($content['carousel_slides'] as $slide)
            <div class="fc-banner-slide @if(empty($slide['image_path'])) fc-banner-slide--fallback @endif" data-fc-banner="{{ $loop->index }}" @if(!$loop->first) hidden @endif>
                @if(!empty($slide['image_path']))
                    <a class="fc-artwork-link" href="{{ asset($slide['image_path']) }}" data-artwork-open aria-label="Enlarge artwork: {{ $slide['title'] }}"><img src="{{ asset($slide['image_path']) }}" alt="{{ $slide['title'] }}" loading="lazy"><span class="fc-artwork-hint">View full artwork @include('partials.ui-arrow')</span></a>
                @else
                    <div class="fc-banner-fallback"><span>UNIVERSITY OF MAKATI · CCIS FACULTY CLUB</span><strong>{{ $slide['title'] }}</strong></div>
                @endif
            </div>
        @endforeach
    </div>
    <div class="fc-container">
    <div class="institution-line"><span>UNIVERSITY OF MAKATI</span><span>COLLEGE OF COMPUTING &amp; INFORMATION SCIENCES</span></div>
    <div class="fc-hero-grid">
        <div class="fc-hero-copy">
            <div data-editorial-slides class="fc-slide-track" aria-live="off">
            @foreach($content['carousel_slides'] as $slide)
                <article class="fc-slide" data-fc-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($content['carousel_slides']) }}" @if(!$loop->first) hidden @endif>
                    <p class="eyebrow"><span class="live-dot"></span> {{ $slide['eyebrow'] }}</p>
                    <h2>{{ $slide['title'] }}</h2>
                    <p class="fc-lead">{{ $slide['body'] }}</p>
                </article>
            @endforeach
            </div>
            <a class="button button-primary" href="{{ route('login') }}">Faculty &amp; Staff Login @include('partials.ui-arrow')</a>
            <div class="fc-slide-controls" data-fc-controls hidden aria-label="Featured messages">
                @foreach($content['carousel_slides'] as $slide)<button type="button" data-editorial-slide="{{ $loop->index }}" data-fc-go="{{ $loop->index }}" aria-label="Show message {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</button>@endforeach
                <button type="button" data-fc-prev aria-label="Previous highlight">&#8592;</button><button type="button" data-fc-next aria-label="Next highlight">&#8594;</button><button type="button" data-fc-pause aria-label="Pause highlights">&#8545;</button>
                <span class="visually-hidden" data-fc-status aria-live="polite" aria-atomic="true"></span>
            </div>
        </div>
    </div>
    <div class="fc-trust-line"><span>Built around your everyday.</span><strong>Faculty coordination</strong><strong>Transparent contributions</strong><strong>Protected access</strong></div>
    </div>
</section>
<section class="fc-about fc-container" id="about">
    <p class="eyebrow">01 / {{ $content['about']['eyebrow'] }}</p>
    <div class="fc-editorial"><h2>{{ $content['about']['title'] }}</h2><div><p>{{ $content['about']['body'] }}</p><div class="fc-chips">@foreach($content['about']['strip'] as $item)<span>{{ $item }}</span>@endforeach</div></div></div>
    <div class="fc-benefits">@foreach($content['benefits'] as $benefit)<article><span class="fc-metadata">0{{ $loop->iteration }}</span><h3>{{ $benefit['title'] }}</h3><p>{{ $benefit['body'] }}</p></article>@endforeach</div>
</section>
<section class="fc-water fc-container" id="features">
    <div class="fc-water-art"><p class="eyebrow">A CLEARER CONTRIBUTION JOURNEY</p><h3>Every step.<br>In one place.</h3><ol class="fc-contribution-steps"><li><span>01</span><div><strong>View your collection</strong><p>Find payment instructions and contribution details.</p></div></li><li><span>02</span><div><strong>Submit your payment</strong><p>Follow the available payment method and verification steps.</p></div></li><li><span>03</span><div><strong>Keep your records close</strong><p>Review your status, history, and available receipts.</p></div></li></ol></div>
    <div class="fc-feature-copy"><p class="eyebrow">02 / WATER CONTRIBUTIONS</p><h2>Less follow-up.<br>More clarity.</h2><p>From payment instructions to a verified receipt, keep every contribution in view.</p><p class="fc-note">Available payment methods and verification steps are shown within each collection. Your personal records stay inside the protected portal.</p><a class="fc-text-link" href="{{ route('login') }}">View your contributions @include('partials.ui-arrow')</a></div>
</section>
<section class="fc-security fc-container"><div><p class="eyebrow">03 / CONFIDENCE, BUILT IN</p><h2>A connected club.<br>A protected<br><em>community.</em></h2><p>Access designed around the people who belong here.</p></div><div class="fc-security-list">
@foreach(['Admin-created accounts' => 'Membership starts with an account issued by your administrator.', 'Two-factor authentication' => 'An additional verification step protects access to your account.', 'Trusted browsers' => 'Manage the browsers you choose to trust.', 'Role-based access' => 'Each role has access to the tools it is authorized to use.', 'Account security controls' => 'Manage passwords, recovery codes and active sessions.'] as $title => $description)
<details @if($loop->first) open @endif><summary><span>0{{ $loop->iteration }}</span>{{ $title }}<b aria-hidden="true">+</b></summary><p>{{ $description }}</p></details>
@endforeach
</div></section>
<section class="fc-roles fc-container"><div class="fc-section-top"><p class="eyebrow">04 / A WORKSPACE FOR YOUR ROLE</p><span>Shared purpose. Focused tools.</span></div><h2>Your day, with<br>the right tools at hand.</h2>
<div class="fc-role-layout"><div class="fc-role-buttons" aria-label="Preview a portal role">
@foreach(['faculty'=>'Faculty','secretary'=>'Secretary','treasurer'=>'Treasurer','admin'=>'Administrator'] as $key=>$label)<button data-role-preview="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="role-{{ $key }}"><span class="fc-role-label">{{ $label }}</span>@include('partials.ui-arrow')</button>@endforeach
</div><div class="fc-role-panels">
@foreach(['faculty'=>['Your faculty essentials','Personal contributions','Published announcements','Profile & security'], 'secretary'=>['Keep the community informed','Manage announcements','Faculty directory','Personal contributions'], 'treasurer'=>['A clearer view of contributions','Payment verification queue','Collection records','Water collection reports'], 'admin'=>['Coordinate with confidence','Member accounts & roles','Account status management','Landing-page content']] as $key=>$items)
<article id="role-{{ $key }}" data-role-panel="{{ $key }}" @if(!$loop->first) hidden @endif><div class="fc-preview-top"><strong>FacultyConnect / {{ ucfirst($key) }}</strong><span class="fc-tag">Illustrative preview</span></div><div class="fc-role-content"><p class="fc-metadata">YOUR WORKSPACE</p><h3>{{ $items[0] }}</h3>@foreach(array_slice($items,1) as $item)<div class="fc-role-item"><span>0{{ $loop->iteration }}</span><strong>{{ $item }}</strong>@include('partials.ui-arrow')</div>@endforeach</div></article>
@endforeach
</div></div>
<div class="fc-cms-metrics">@foreach($content['portal_preview']['metrics'] as $metric)<div><strong>{{ $metric['title'] }}</strong><span>{{ $metric['body'] }}</span></div>@endforeach<div class="fc-mini-bars" aria-hidden="true">@foreach($content['portal_preview']['bars'] as $width)<i style="width:{{ max(0,min(100,(int)$width)) }}%"></i>@endforeach</div></div>
</section>
<section class="fc-community fc-container" data-dot-section><div class="fc-microdots fc-microdots--accent" data-microdots="accent" aria-hidden="true"><canvas aria-hidden="true"></canvas></div><div class="fc-community-copy"><p class="eyebrow">05 / PEOPLE &amp; PERSPECTIVES</p><h2>Closer to the people<br>behind CCIS.</h2><p>Club updates, faculty records and shared information, thoughtfully brought together.</p><div class="fc-directory-sample"><span class="fc-avatar">CC</span><div><strong>Faculty directory</strong><small>CCIS · Available to authorized roles</small></div><span class="fc-tag">Preview</span></div></div><article class="fc-announcement"><p class="fc-metadata">THE FACULTY BULLETIN / PREVIEW</p><span class="fc-announcement-symbol" aria-hidden="true">@include('partials.ui-arrow')</span><h3>One place for<br>the updates<br>that matter.</h3><p>Read published club announcements inside your faculty workspace.</p><a href="{{ route('login') }}">Open your portal @include('partials.ui-arrow')</a></article></section>
<section class="fc-roadmap fc-container"><div class="fc-section-top"><p class="eyebrow">EXPLORE THE PORTAL</p><span>Module information managed by your club</span></div><div class="fc-feature-list">@foreach($content['features'] as $feature)<article><span>{{ $feature['icon'] }}</span><h3>{{ $feature['title'] }}</h3><small>{{ $feature['status'] }}</small></article>@endforeach</div></section>
<section class="fc-cta fc-container" data-dot-section><div class="fc-microdots fc-microdots--accent" data-microdots="accent" aria-hidden="true"><canvas aria-hidden="true"></canvas></div><p class="eyebrow">YOUR COMMUNITY. YOUR CONNECTION.</p><h2>{{ $content['cta']['title'] }}</h2><a class="button button-secondary" href="{{ route('login') }}">{{ $content['cta']['login_label'] }} @include('partials.ui-arrow')</a><span class="fc-cta-caption">CCIS FACULTY CLUB · UNIVERSITY OF MAKATI</span></section>
</main>
<dialog class="fc-artwork-dialog fc-liquid-glass fc-glass-elevated" data-artwork-dialog aria-labelledby="artwork-title"><div class="fc-artwork-toolbar"><div><h2 id="artwork-title">Faculty artwork</h2><p id="artwork-help">Use Enlarge to read names, then scroll or pinch to explore.</p></div><div class="fc-artwork-actions"><button type="button" data-artwork-zoom aria-pressed="false">Enlarge</button><button type="button" data-artwork-close aria-label="Close artwork">&#215;</button></div></div><div class="fc-artwork-viewport" tabindex="0" role="region" aria-label="Enlarged faculty artwork" aria-describedby="artwork-help"><img data-artwork-image alt=""></div></dialog>
<footer class="fc-footer fc-container"><div class="fc-footer-top">@include('partials.brand',['href'=>'#home'])<p>College of Computing and Information Sciences<br>University of Makati</p><nav aria-label="Footer"><a href="#about">About</a><a href="#features">Features</a><a href="{{ route('login') }}">Login @include('partials.ui-arrow')</a></nav></div><div class="fc-wordmark" aria-hidden="true">FacultyConnect<span>®</span></div><div class="fc-footer-bottom"><span>Built for the CCIS faculty community.</span><span>© {{ date('Y') }} FacultyConnect</span></div></footer>
@endsection
@push('scripts')<script src="{{ asset('js/landing.js') }}" defer></script><script src="{{ asset('js/microdots.js') }}" defer></script>@endpush
