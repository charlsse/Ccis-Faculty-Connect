<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - FacultyConnect</title>
    @include('partials.appearance-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/facultyconnect.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appearance.css') }}">
    <script src="{{ asset('js/appearance.js') }}" defer></script>
    <script src="{{ asset('js/carousels.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('css/privacy.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body class="auth-page">
    <div class="fc-microdots fc-microdots--auth" data-microdots="auth" aria-hidden="true"><canvas aria-hidden="true"></canvas></div>
    <div class="fc-auth-appearance">@include('partials.theme-toggle')</div>
    <a class="skip-link" href="#main-content">Skip to content</a><main class="auth-shell" id="main-content">
        <section class="auth-panel auth-identity" aria-label="FacultyConnect identity">
            @include('partials.brand', ['class' => 'auth-brand'])
            <div>
                <p class="eyebrow">University of Makati</p>
                <h1>College of Computing and Information Sciences</h1>
                <p>Secure access for the CCIS Faculty Club community, designed for organized records, transparent updates, and future-ready faculty services.</p>
            </div>
            <div class="identity-points" aria-label="Portal highlights">
                <span>Secure sessions</span>
                <span>Faculty-first workflows</span>
                <span>Role-based access</span>
            </div>
        </section>

        <section class="auth-panel auth-form-panel">
            @yield('content')
            <p class="auth-switch"><a href="{{ route('privacy.public') }}">Privacy &amp; policy information</a></p>
        </section>
    </main>
    <script src="{{ asset('js/facultyconnect.js') }}" defer></script>
    <script src="{{ asset('js/microdots.js') }}" defer></script>
    <script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
</body>
</html>
