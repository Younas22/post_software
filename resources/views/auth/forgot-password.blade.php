<x-layouts.guest :title="'Forgot password'">
    <x-card>
        <h1 class="mb-1 text-xl font-semibold text-neutral-900 dark:text-white">Forgot your password?</h1>
        <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">
            Enter your email and we'll send you a password reset link.
        </p>

        @session('status')
            <x-alert variant="success" class="mb-6">{{ $value }}</x-alert>
        @endsession

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-button type="submit" class="w-full">Email password reset link</x-button>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('login') }}" class="underline hover:text-neutral-900 dark:hover:text-white">Back to log in</a>
        </p>
    </x-card>
</x-layouts.guest>
