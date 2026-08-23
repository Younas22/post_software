<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Already-authenticated users hitting a guest route (e.g. /login) are
        // sent to their own area's dashboard rather than a generic '/'.
        RedirectIfAuthenticated::redirectUsing(function () {
            $user = Auth::user();

            return $user?->is_super_admin
                ? route('admin.dashboard')
                : route('client.dashboard');
        });

        // Applies everywhere Rules\Password::defaults() is used (currently
        // NewPasswordController's reset form). `uncompromised()` is
        // deliberately omitted — it calls the HaveIBeenPwned API over HTTP
        // during validation, an external dependency this app shouldn't
        // require to function (including offline dev/CI).
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers()->symbols());

        // Outside local/debug environments, Scramble's RestrictedDocsAccess
        // middleware checks this gate before serving /docs/api — only a
        // logged-in Super Admin may view the live API reference in
        // production; everyone else gets a 403, never a public docs page.
        Gate::define('viewApiDocs', fn ($user = null) => (bool) ($user?->is_super_admin));
    }
}
