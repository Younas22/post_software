<x-layouts.guest :title="'Reset password'">
    <x-card>
        <h1 class="mb-1 text-xl font-semibold text-neutral-900 dark:text-white">Reset password</h1>
        <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">
            Choose a new password for your account.
        </p>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" type="email" name="email" :value="old('email', $email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="password" value="New password" />
                <x-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-label for="password_confirmation" value="Confirm new password" />
                <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

            <x-button type="submit" class="w-full">Reset password</x-button>
        </form>
    </x-card>
</x-layouts.guest>
