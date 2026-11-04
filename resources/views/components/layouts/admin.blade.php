@props(['breadcrumbs' => [], 'title' => null])

@php
$pageTitle = $title ?? (count($breadcrumbs) ? end($breadcrumbs)['label'] : 'Admin');
$user = auth()->user();

$nav = [
    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
    ['label' => 'Clients', 'icon' => 'users', 'route' => 'admin.clients.index', 'pattern' => 'admin.clients.*'],
    ['label' => 'Businesses', 'icon' => 'building', 'route' => 'admin.businesses.index', 'pattern' => 'admin.businesses.*'],
];

$saasNav = [
    ['label' => 'Packages', 'icon' => 'box', 'route' => 'admin.packages.index', 'pattern' => 'admin.packages.*'],
    ['label' => 'Features', 'icon' => 'puzzle', 'route' => 'admin.features.index', 'pattern' => 'admin.features.*'],
    ['label' => 'Subscriptions', 'icon' => 'credit-card', 'route' => 'admin.subscriptions.index', 'pattern' => 'admin.subscriptions.*'],
    ['label' => 'Devices', 'icon' => 'monitor', 'route' => 'admin.devices.index', 'pattern' => 'admin.devices.*'],
];

$configNav = [
    ['label' => 'Business Types', 'icon' => 'tag', 'route' => 'admin.business-types.index', 'pattern' => 'admin.business-types.*'],
    ['label' => 'Settings', 'icon' => 'cog', 'route' => 'admin.settings.index', 'pattern' => 'admin.settings.*'],
];

$systemNav = [
    ['label' => 'Activity Logs', 'icon' => 'clipboard-list', 'route' => 'admin.activity-logs.index', 'pattern' => 'admin.activity-logs.*'],
];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $pageTitle.' · '.app(\App\Support\Branding::class)->name()])

    {{-- Sidebar collapse state, applied pre-paint to avoid a flash of the
         wrong width (mirrors the theme pre-paint script in the shared head
         partial). Scoped to the admin layout only. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('admin-sidebar-collapsed') === '1') {
                    document.documentElement.classList.add('admin-sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="flex min-h-screen">
        {{-- Mobile/tablet drawer backdrop --}}
        <div id="admin-sidebar-backdrop" data-sidebar-backdrop class="fixed inset-0 z-40 bg-black/40"></div>

        {{-- Sidebar --}}
        <aside id="admin-sidebar" class="flex w-64 flex-col border-r border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-neutral-200 px-4 dark:border-neutral-800">
                <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2">
                    @php($branding = app(\App\Support\Branding::class))
                    @if ($branding->logoUrl())
                        <img src="{{ $branding->logoUrl() }}" alt="" class="h-8 w-8 shrink-0 rounded-md border border-neutral-200 bg-white object-contain p-0.5 dark:border-neutral-700">
                    @else
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-neutral-900 text-xs font-bold text-white dark:bg-white dark:text-neutral-900">
                            {{ $branding->initials() }}
                        </span>
                    @endif
                    <span id="admin-sidebar-brand-text" class="truncate text-sm font-semibold text-neutral-900 dark:text-white" title="{{ $branding->name() }}">
                        {{ $branding->name() }}
                    </span>
                </a>

                <button type="button" data-sidebar-collapse-toggle aria-label="Collapse sidebar" class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 lg:inline-flex dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-white">
                    <x-icon name="panel-left" class="h-4.5 w-4.5" />
                </button>

                <button type="button" data-drawer-close aria-label="Close menu" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 lg:hidden dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-white">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
                @foreach ($nav as $item)
                    <x-nav-link :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])">
                        {{ $item['label'] }}
                    </x-nav-link>
                @endforeach

                <x-nav-group-label>SaaS</x-nav-group-label>
                @foreach ($saasNav as $item)
                    <x-nav-link :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])">
                        {{ $item['label'] }}
                    </x-nav-link>
                @endforeach

                <x-nav-group-label>Configuration</x-nav-group-label>
                @foreach ($configNav as $item)
                    <x-nav-link :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])">
                        {{ $item['label'] }}
                    </x-nav-link>
                @endforeach

                <x-nav-group-label>System</x-nav-group-label>
                @foreach ($systemNav as $item)
                    <x-nav-link :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs($item['pattern'])">
                        {{ $item['label'] }}
                    </x-nav-link>
                @endforeach
            </nav>

            <div class="border-t border-neutral-200 p-3 dark:border-neutral-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="group flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white">
                        <x-icon name="logout" class="h-5 w-5 shrink-0" />
                        <span data-sidebar-label>Log out</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-neutral-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:border-neutral-800 dark:bg-neutral-900/80">
                <button type="button" data-drawer-toggle aria-label="Open menu" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 lg:hidden dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white">
                    <x-icon name="menu" class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1">
                    @if (count($breadcrumbs))
                        <x-breadcrumb :items="$breadcrumbs" />
                    @else
                        <h1 class="truncate text-sm font-semibold text-neutral-900 dark:text-white">{{ $pageTitle }}</h1>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                    {{-- Notifications: real data only — this user's genuine unread
                         notifications, shown with an honest empty state rather
                         than a fabricated badge count. --}}
                    <x-dropdown align="right">
                        <x-slot:trigger>
                            <span class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white">
                                <x-icon name="bell" class="h-5 w-5" />
                                @if ($user && $user->unreadNotifications->isNotEmpty())
                                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500"></span>
                                @endif
                            </span>
                        </x-slot:trigger>

                        <div class="border-b border-neutral-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:border-neutral-800 dark:text-neutral-600">
                            Notifications
                        </div>

                        @forelse ($user?->unreadNotifications->take(5) ?? [] as $notification)
                            <div class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-300">
                                {{ $notification->data['message'] ?? 'New notification' }}
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-neutral-400 dark:text-neutral-600">
                                No notifications yet.
                            </p>
                        @endforelse
                    </x-dropdown>

                    <x-theme-toggle />

                    <x-dropdown align="right">
                        <x-slot:trigger>
                            <span class="flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-900 text-xs font-semibold text-white dark:bg-white dark:text-neutral-900">
                                    {{ $user ? strtoupper(substr($user->name, 0, 1)) : '?' }}
                                </span>
                                <span class="hidden text-sm font-medium text-neutral-700 sm:inline dark:text-neutral-300">
                                    {{ $user->name ?? '' }}
                                </span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 text-neutral-400 sm:inline" />
                            </span>
                        </x-slot:trigger>

                        <div class="border-b border-neutral-100 px-4 py-2 dark:border-neutral-800">
                            <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $user->name ?? '' }}</p>
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email ?? '' }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link type="submit">Log out</x-dropdown-link>
                        </form>
                    </x-dropdown>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
