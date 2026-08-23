<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="flex min-h-screen flex-col">
        <header class="flex items-center justify-between px-6 py-5 sm:px-10">
            <span class="text-sm font-semibold tracking-tight text-neutral-900 dark:text-white">
                Universal POS SaaS
            </span>
            <x-theme-toggle />
        </header>

        <main class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-sm sm:max-w-md">
                {{ $slot }}
            </div>
        </main>

        <footer class="px-6 pb-6 text-center text-xs text-neutral-400 dark:text-neutral-600">
            &copy; {{ now()->year }} Universal POS SaaS
        </footer>
    </div>
</body>
</html>
