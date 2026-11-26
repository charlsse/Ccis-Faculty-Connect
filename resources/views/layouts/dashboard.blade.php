<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - FacultyConnect</title>
    @include('partials.appearance-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/facultyconnect.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/water.css') }}">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appearance.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payment-experience.css') }}">
    <script src="{{ asset('js/appearance.js') }}" defer></script>
    <script src="{{ asset('js/carousels.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/workspace-updates.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payment-sheet.css') }}">
    <script src="{{ asset('js/workspace-updates.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/privacy.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payments-refined.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payment-navigation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body class="dashboard-page">
    <a class="skip-link" href="#main-content">Skip to content</a><div class="dashboard-shell">
        <aside class="dashboard-sidebar fc-liquid-glass fc-glass-regular" id="dashboardSidebar" aria-label="Dashboard navigation">
            <div class="sidebar-head">
                @include('partials.brand', ['class' => 'sidebar-brand'])
                <button class="icon-button sidebar-close" data-sidebar-close type="button" aria-label="Close menu"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="sidebar-scroll" tabindex="0" aria-label="Scrollable workspace navigation">
            <nav class="sidebar-nav" aria-label="Your workspace"><span class="nav-caption">YOUR WORKSPACE</span>
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">@include('partials.workspace-icon', ['name' => 'dashboard'])<span>Dashboard</span></a>
                <a class="{{ request()->routeIs('payments.*', 'water.*', 'wallet.*') ? 'active' : '' }}" href="{{ route('water.index') }}">@include('partials.workspace-icon', ['name' => 'payments'])<span>Payments &amp; Collections</span></a>
                <a class="{{ request()->routeIs('announcements.*') ? 'active' : '' }}" href="{{ route('announcements.index') }}">@include('partials.workspace-icon', ['name' => 'announcements'])<span>Announcements</span></a>
                @if(auth()->user()->hasPermission('directory.view'))
                    <a class="{{ request()->routeIs('directory.*') ? 'active' : '' }}" href="{{ route('directory.index') }}">@include('partials.workspace-icon', ['name' => 'directory'])<span>Faculty Directory</span></a>
                @endif
                @if (auth()->user()->isAdmin())
                    <a class="{{ request()->routeIs('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}">@include('partials.workspace-icon', ['name' => 'members'])<span>User Accounts</span></a>
                    <a class="{{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.edit') }}">@include('partials.workspace-icon', ['name' => 'content'])<span>Content Manager</span></a>
                @endif
            </nav>
            <div class="sidebar-context"><span class="eyebrow">CCIS Faculty Club</span><strong>{{ auth()->user()->role_label }} workspace</strong><span>University of Makati</span></div>
            </div>


        </aside>

        <div class="sidebar-overlay" data-sidebar-close aria-hidden="true"></div>

        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <div class="fc-workspace-bar-material fc-liquid-glass fc-glass-clear" data-glass-reflection aria-hidden="true"></div>
                <button class="icon-button menu-toggle" data-sidebar-open type="button" aria-label="Open menu"><span></span><span></span><span></span></button>
                <div class="workspace-location"><span>WORKSPACE</span><strong>@yield('title', 'Dashboard')</strong></div>
                <div class="topbar-date">{{ $currentDate ?? now()->format('F j, Y') }}</div>
                <div class="topbar-actions">
                    @include('partials.theme-toggle')
                    @include('dashboard.partials.notification-menu')
                    <div class="profile-menu">
                        <button class="profile-button" data-profile-toggle type="button" aria-expanded="false" aria-controls="profile-dropdown" aria-label="Open account menu">
                            <img class="avatar" src="{{ auth()->user()->profile_photo_url }}" alt="">
                            <span class="profile-summary">
                                <strong>{{ auth()->user()->first_name }}</strong>
                                <small>{{ auth()->user()->role_label }}</small>
                            </span>
                        </button>
                        <div class="profile-dropdown fc-liquid-glass fc-glass-elevated" id="profile-dropdown" data-profile-menu>
                            <p>{{ auth()->user()->full_name }}</p>
                            <small>{{ auth()->user()->role_label }}</small><hr>
                            <a href="{{ route('profile.show') }}">View profile</a>
                            <a href="{{ route('security.show') }}">Security settings</a>
                            <a href="{{ route('privacy.index') }}">Privacy &amp; Security</a>
                            <hr><form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">Logout</button>
                            </form>
                            <form method="POST" action="{{ route('logout.forget-device') }}">
                                @csrf
                                <button class="forget-device-action" type="submit">Logout &amp; forget device<small>Require verification on this device next time</small></button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-content" id="main-content">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="{{ asset('js/facultyconnect.js') }}" defer></script>
    <script src="{{ asset('js/dashboard.js') }}" defer></script>
    <script src="{{ asset('js/water.js') }}" defer></script>
    <script src="{{ asset('js/microdots.js') }}" defer></script>
    <script src="{{ asset('js/workspace.js') }}" defer></script>
    <script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
</body>
</html>

