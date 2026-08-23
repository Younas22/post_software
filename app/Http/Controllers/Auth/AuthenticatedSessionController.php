<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        $logger->log('login', $user, "{$user->name} logged in");

        return redirect()->intended(
            $user->is_super_admin ? route('admin.dashboard') : route('client.dashboard')
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = Auth::user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            $logger->log('logout', $user, "{$user->name} logged out", actor: $user);
        }

        return redirect()->route('login');
    }
}
