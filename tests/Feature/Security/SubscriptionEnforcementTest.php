<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionEnforcementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionEnforcementService
    {
        return app(SubscriptionEnforcementService::class);
    }

    public function test_suspended_client_has_no_platform_access_even_with_an_active_subscription(): void
    {
        $client = Client::factory()->create(['status' => 'suspended']);
        Subscription::factory()->create(['client_id' => $client->id, 'status' => 'active']);

        $this->assertFalse($this->service()->hasPlatformAccess($client));
    }

    public function test_active_client_with_no_subscription_has_no_platform_access(): void
    {
        $client = Client::factory()->create(['status' => 'active']);

        $this->assertFalse($this->service()->hasPlatformAccess($client));
    }

    public function test_active_client_with_active_or_trial_subscription_has_platform_access(): void
    {
        $activeClient = Client::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['client_id' => $activeClient->id, 'status' => 'active']);

        $trialClient = Client::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['client_id' => $trialClient->id, 'status' => 'trial']);

        $this->assertTrue($this->service()->hasPlatformAccess($activeClient));
        $this->assertTrue($this->service()->hasPlatformAccess($trialClient));
    }

    public function test_expired_subscription_reports_correctly(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['client_id' => $client->id, 'status' => 'active', 'ends_at' => now()->subDay()]);

        $this->assertTrue($this->service()->hasSubscriptionExpired($client));
        $this->assertFalse($this->service()->hasPlatformAccess($client));
    }

    public function test_device_limit_check_delegates_to_device_activation_service(): void
    {
        $client = Client::factory()->create();
        $package = Package::factory()->create(['max_devices' => 1]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = \App\Models\Branch::factory()->create(['business_id' => $business->id]);
        \App\Models\Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);

        $this->assertTrue($this->service()->isDeviceLimitReached($client));
    }

    public function test_client_area_is_blocked_when_subscription_is_not_active(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        $client = Client::factory()->create(['status' => 'active']);
        // No subscription at all.
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();
        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $client->id]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertStatus(402);
    }

    public function test_client_area_is_accessible_once_subscription_is_active(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        $client = Client::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();
        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $client->id]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
    }
}
