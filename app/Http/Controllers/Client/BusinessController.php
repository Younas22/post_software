<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Minimal tenant-scoped business detail page. `$business` is route-model
     * bound purely from the URL {business} id — the BusinessPolicy (invoked
     * via authorize()) is what actually decides whether the authenticated
     * user's own client_id matches, never a client_id/business_id taken from
     * the request itself.
     */
    public function show(Business $business): View
    {
        $this->authorize('view', $business);

        return view('client.businesses.show', ['business' => $business]);
    }
}
