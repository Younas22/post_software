<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\ActivityLogger;
use App\Services\DeviceActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Device::class);

        $devices = Device::query()->with(['client', 'business', 'branch'])->latest()->paginate(15);

        return view('admin.devices.index', ['devices' => $devices]);
    }

    public function show(Device $device, DeviceActivationService $devices): View
    {
        $this->authorize('view', $device);

        $device->load(['client', 'business', 'branch']);

        return view('admin.devices.show', [
            'device' => $device,
            'deviceLimit' => $devices->limitFor($device->client),
            'activeCount' => $devices->activeCount($device->client),
        ]);
    }

    public function activate(Device $device, DeviceActivationService $devices, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('manage', $device);

        if ($device->status !== 'active' && ! $devices->canActivate($device->client, excluding: $device)) {
            $limit = $devices->limitFor($device->client);

            return back()->withErrors([
                'device' => "This client's package allows at most {$limit} active device(s). Deactivate another device first, or upgrade the package.",
            ]);
        }

        $device->update([
            'status' => 'active',
            'activated_at' => $device->activated_at ?? now(),
        ]);

        $logger->log('device.activated', $device, "Device \"{$device->name}\" activated");

        return back()->with('status', 'Device activated.');
    }

    public function deactivate(Device $device): RedirectResponse
    {
        $this->authorize('manage', $device);

        $device->update(['status' => 'inactive']);

        return back()->with('status', 'Device deactivated.');
    }

    public function block(Device $device, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('manage', $device);

        $device->update(['status' => 'blocked']);

        $logger->log('device.blocked', $device, "Device \"{$device->name}\" blocked");

        return back()->with('status', 'Device blocked.');
    }
}
