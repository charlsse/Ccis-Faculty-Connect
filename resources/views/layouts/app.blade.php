<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FacultyConnect')</title>
    @include('partials.appearance-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/facultyconnect.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appearance.css') }}">
    <script src="{{ asset('js/appearance.js') }}" defer></script>
    <script src="{{ asset('js/carousels.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body class="@yield('bodyClass')">
    @yield('content')
    <script src="{{ asset('js/facultyconnect.js') }}" defer></script>
    @stack('scripts')
    <script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
</body>
</html>
