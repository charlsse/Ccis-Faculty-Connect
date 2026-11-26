@php
    $showText = $showText ?? true;
    $href = $href ?? route('landing');
    $class = $class ?? '';
    $logoPath = public_path('images/ccis-logo.png');
@endphp

<a class="brand-lockup {{ $class }}" href="{{ $href }}" aria-label="FacultyConnect home">
    <span class="brand-logo" aria-hidden="true">
        @if (file_exists($logoPath))
            <img src="{{ asset('images/ccis-logo.png') }}" alt="" loading="lazy">
        @else
            <span>CCIS</span>
        @endif
    </span>
    @if ($showText)
        <span class="brand-copy">
            <strong>FacultyConnect</strong>
            <small>CCIS Faculty Club</small>
        </span>
    @endif
</a>
