@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'payments' => 'M3 6h18v14H3z M3 10h18 M6 16h4 M6 3h12',
        'announcements' => 'M4 10v5h4l10 4V6L8 10H4z M8 15l2 6 M21 9v7',
        'directory' => 'M4 3h16v18H4z M8 8h8 M8 12h8 M8 16h5',
        'members' => 'M16 21v-3a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v3 M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-3a4 4 0 0 0-3-3.87 M17 2.13a4 4 0 0 1 0 7.75',
        'content' => 'M4 3h16v18H4z M8 7h8 M8 11h3v5H8z M14 11h2 M14 15h2',
    ];
@endphp
<svg class="workspace-nav-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] }}"/></svg>
