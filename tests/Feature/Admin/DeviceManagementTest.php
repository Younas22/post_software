<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Device;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DeviceActivationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function clientWithDeviceLimit(int $limit): Client
    {
        $client = Client::factory()->create();
        $package = Package::factory()->create(['max_devices' => $limit]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);

        return $client;
    }

    public function test_device_service_allows_activation_below_the_package_limit(): void
    {
        $client = $this->clientWithDeviceLimit(2);
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);

        $service = app(DeviceActivationService::class);

        $this->assertSame(2, $service->limitFor($client));
        $this->assertSame(1, $service->activeCount($client));
        $this->assertTrue($service->canActivate($client));
    }

    public function test_device_service_rejects_activation_at_the_package_limit(): void
    {
        $client = $this->clientWithDeviceLimit(1);
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $pending = Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'inactive']);

        $service = app(DeviceActivationService::class);

        $this->assertFalse($service->canActivate($client));
        // Excluding the device itself (re-activating an already-active one)
        // must not double-count it against its own limit.
        $this->assertFalse($service->canActivate($client, excluding: $pending));
    }

    public function test_device_service_treats_null_max_devices_as_unlimited(): void
    {
        $client = $this->clientWithDeviceLimit(0); // will override below
        $package = Package::factory()->create(['max_devices' => null]);
        Subscription::query()->where('client_id', $client->id)->update(['package_id' => $package->id]);

        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        Device::factory()->count(5)->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);

        $service = app(DeviceActivationService::class);

        $this->assertNull($service->limitFor($client));
        $this->assertTrue($service->canActivate($client));
    }

    public function test_activation_is_rejected_via_the_controller_when_the_limit_is_reached(): void
    {
        $client = $this->clientWithDeviceLimit(1);
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $pending = Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'inactive']);

        $response = $this->actingAs($this->admin())->patch(route('admin.devices.activate', $pending));

        $response->assertSessionHasErrors('device');
        $this->assertSame('inactive', $pending->fresh()->status);
    }

    public function test_super_admin_can_activate_deactivate_and_block_a_device(): void
    {
        $device = Device::factory()->create(['status' => 'inactive']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.devices.activate', $device))->assertRedirect();
        $fresh = $device->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->activated_at);

        $this->actingAs($admin)->patch(route('admin.devices.deactivate', $device))->assertRedirect();
        $this->assertSame('inactive', $device->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.devices.block', $device))->assertRedirect();
        $this->assertSame('blocked', $device->fresh()->status);
    }

    public function test_client_side_role_cannot_view_another_clients_device(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherDevice = Device::factory()->create(['client_id' => $otherClient->id]);

        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $ownClient->id]);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('devices.view'));
        $this->assertFalse($user->can('view', $otherDevice));
    }
}
