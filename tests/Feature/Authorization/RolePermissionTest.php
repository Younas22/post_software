<?php

namespace Tests\Feature\Authorization;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create(array_merge(['is_super_admin' => false], $attributes));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_super_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_client_owner_cannot_access_admin_area(): void
    {
        $owner = $this->userWithRole('client-owner');

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_super_admin_cannot_access_client_area(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->get(route('client.dashboard'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_protected_routes(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('client.dashboard'))->assertRedirect(route('login'));
    }

    public function test_client_owner_lacks_platform_client_management_permission(): void
    {
        $owner = $this->userWithRole('client-owner');

        // clients.* is a Super-Admin-only permission — Client Owner was never
        // granted it (see RolePermissionSeeder), so even a raw permission
        // check must fail, independent of the area middleware.
        $this->assertFalse($owner->hasPermission('clients.view'));
        $this->assertFalse($owner->hasPermission('packages.manage'));
        $this->assertFalse($owner->hasPermission('subscriptions.manage'));
    }

    public function test_cashier_without_businesses_permission_is_forbidden_from_business_page(): void
    {
        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->create(['business_id' => $business->id]);

        // Cashier only has dashboard.view + devices.view (see RolePermissionSeeder) —
        // not businesses.view — even though the business belongs to their own tenant.
        $cashier = $this->userWithRole('cashier', ['client_id' => $client->id]);

        $response = $this->actingAs($cashier)->get(route('client.businesses.show', $business));

        $response->assertForbidden();
    }

    public function test_manager_with_businesses_permission_can_view_own_business(): void
    {
        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->create(['business_id' => $business->id]);

        $manager = $this->userWithRole('manager', ['client_id' => $client->id]);

        $response = $this->actingAs($manager)->get(route('client.businesses.show', $business));

        $response->assertOk();
    }
}
