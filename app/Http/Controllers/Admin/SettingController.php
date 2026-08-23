<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Super Admin system-wide settings — Application Name, Logo, Default
     * Currency/Timezone, Support Email/Phone, Maintenance Mode, Default
     * Theme — all resolved through SettingsService against the 'system'
     * scope of the registry in config/settings.php.
     */
    public function index(SettingsService $settings): View
    {
        $this->authorize('viewAny', \App\Models\Setting::class);

        $systemKeys = $settings->keysForScope('system');
        $values = collect($systemKeys)->mapWithKeys(fn ($definition, $key) => [$key => $settings->resolve($key)]);

        return view('admin.settings.index', [
            'systemKeys' => $systemKeys,
            'values' => $values,
        ]);
    }

    public function update(Request $request, SettingsService $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', \App\Models\Setting::class);

        foreach (array_keys($settings->keysForScope('system')) as $key) {
            if ($request->has($key)) {
                $settings->set($key, $request->input($key));
            }
        }

        $logger->log('settings.updated', null, 'System settings updated');

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated.');
    }
}
