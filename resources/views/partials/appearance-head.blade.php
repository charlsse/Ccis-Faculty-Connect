{{-- Apply before styles/body paint; storage may be unavailable in private browsers. --}}
<script>
    (() => {
        let saved;
        try { saved = localStorage.getItem('facultyconnect.theme'); } catch (_) {}
        document.documentElement.dataset.theme = ['light', 'dark'].includes(saved)
            ? saved : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    })();
</script>
@php($siteIcon = \App\Models\SiteContent::branding())
<link rel="icon" type="image/png" href="{{ route('site.icon', ['v' => $siteIcon['version']]) }}">
<link rel="apple-touch-icon" href="{{ route('site.icon', ['v' => $siteIcon['version']]) }}">
<meta name="theme-color" content="#fbfefc" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1711" media="(prefers-color-scheme: dark)">
