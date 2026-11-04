<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@php($branding = app(\App\Support\Branding::class))
<title>{{ $title ?? $branding->name() }}</title>
@if ($branding->faviconUrl())
    <link rel="icon" href="{{ $branding->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ $branding->faviconUrl() }}">
@endif

{{-- Apply the persisted (or system) theme before first paint, to avoid a
     light/dark flash. --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
