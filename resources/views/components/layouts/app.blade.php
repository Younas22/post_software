@props(['area'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold tracking-tight text-neutral-900 dark:text-white">
                        Universal POS SaaS
                    </span>
                    <span class="rounded-full border border-neutral-200 px-2.5 py-0.5 text-xs font-medium text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                        {{ $area }}
                    </span>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    @auth
                        <span class="hidden text-sm text-neutral-600 sm:inline dark:text-neutral-400">
                            {{ auth()->user()->name }}
                        </span>
                    @endauth

                    <x-theme-toggle />

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button variant="secondary" type="submit" class="w-auto! py-1.5! px-3! text-xs">
                            Log out
                        </x-button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
