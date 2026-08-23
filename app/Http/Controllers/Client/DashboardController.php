<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Minimal client-side landing page for Client Owner / Business Admin /
     * Manager / Cashier. The full dashboard is a later phase.
     */
    public function __invoke(): View
    {
        return view('client.dashboard');
    }
}
