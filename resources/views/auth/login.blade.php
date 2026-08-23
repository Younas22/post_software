<x-layouts.guest :title="'Log in'">
    <x-card>
        <h1 class="mb-1 text-xl font-semibold text-neutral-900 dark:text-white">Log in</h1>
        <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">
            Sign in to your Universal POS SaaS account.
        </p>

        @session('status')
            <x-alert variant="success" class="mb-6">{{ $value }}</x-alert>
        @endsession

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                    <input type="checkbox" name="remember" class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:ring-white">
                    Remember me
                </label>

                <a href="{{ route('password.request') }}" class="text-sm text-neutral-600 underline hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">
                    Forgot password?
                </a>
            </div>

            <x-button type="submit" class="w-full">Log in</x-button>
        </form>
    </x-card>
</x-layouts.guest>
