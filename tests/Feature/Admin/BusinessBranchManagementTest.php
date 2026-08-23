<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBranchManagementTest extends TestCase
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

    public function test_super_admin_can_create_a_business_for_a_client(): void
    {
        $client = Client::factory()->create();
        $businessType = BusinessType::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.store'), [
            'client_id' => $client->id,
            'business_type_id' => $businessType->id,
            'name' => 'Downtown Grocery',
            'phone' => '555-0200',
            'email' => 'shop@downtown.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        $business = Business::query()->where('name', 'Downtown Grocery')->firstOrFail();
        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertSame($client->id, $business->client_id);
    }

    public function test_super_admin_can_view_and_update_a_business(): void
    {
        $business = Business::factory()->create(['name' => 'Old Name']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.businesses.show', $business))->assertOk();

        $response = $this->actingAs($admin)->put(route('admin.businesses.update', $business), [
            'client_id' => $business->client_id,
            'business_type_id' => $business->business_type_id,
            'name' => 'New Name',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertSame('New Name', $business->fresh()->name);
    }

    public function test_super_admin_can_create_and_update_a_branch(): void
    {
        $business = Business::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.businesses.branches.store', $business), [
            'name' => 'North Branch',
            'phone' => '555-0300',
            'status' => 'active',
        ]);

        $branch = Branch::query()->where('name', 'North Branch')->firstOrFail();
        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertSame($business->id, $branch->business_id);

        $response = $this->actingAs($admin)->put(route('admin.businesses.branches.update', [$business, $branch]), [
            'name' => 'North Branch Renamed',
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertSame('North Branch Renamed', $branch->fresh()->name);
        $this->assertSame('inactive', $branch->fresh()->status);
    }

    public function test_client_owner_cannot_create_a_branch_on_another_clients_business(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherBusiness = Business::factory()->create(['client_id' => $otherClient->id]);

        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $ownClient->id]);
        $user->roles()->attach($role);

        // Blocked by the `super-admin` area middleware before BranchPolicy
        // even runs — these routes are admin-only in this phase.
        $this->actingAs($user)
            ->get(route('admin.businesses.branches.create', $otherBusiness))
            ->assertForbidden();
    }

    public function test_branch_policy_denies_cross_tenant_update_even_with_the_right_permission(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $ownBusiness = Business::factory()->create(['client_id' => $ownClient->id]);
        $ownBranch = Branch::factory()->create(['business_id' => $ownBusiness->id]);
        $otherBusiness = Business::factory()->create(['client_id' => $otherClient->id]);
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id]);

        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $ownClient->id]);
        $user->roles()->attach($role);

        // Client Owner genuinely holds businesses.update — the only thing
        // standing between them and another tenant's branch is the policy's
        // client_id comparison.
        $this->assertTrue($user->hasPermission('businesses.update'));
        $this->assertTrue($user->can('update', $ownBranch));
        $this->assertFalse($user->can('update', $otherBranch));
    }
}
