<?php

namespace Tests\Feature\Admin;

use App\Models\Business;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
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

    public function test_super_admin_can_create_a_client(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.clients.store'), [
            'name' => 'Acme Retail',
            'email' => 'owner@acme.test',
            'phone' => '555-0100',
            'status' => 'active',
            'notes' => 'Signed up via referral.',
        ]);

        $client = Client::query()->where('email', 'owner@acme.test')->firstOrFail();
        $response->assertRedirect(route('admin.clients.show', $client));
        $this->assertSame('Acme Retail', $client->name);
    }

    public function test_client_creation_requires_a_unique_email(): void
    {
        $existing = Client::factory()->create(['email' => 'dup@example.com']);

        $response = $this->actingAs($this->admin())->post(route('admin.clients.store'), [
            'name' => 'Another Co',
            'email' => 'dup@example.com',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_view_edit_and_update_a_client(): void
    {
        $client = Client::factory()->create(['name' => 'Old Name']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.clients.show', $client))->assertOk();
        $this->actingAs($admin)->get(route('admin.clients.edit', $client))->assertOk();

        $response = $this->actingAs($admin)->put(route('admin.clients.update', $client), [
            'name' => 'New Name',
            'email' => $client->email,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.clients.show', $client));
        $this->assertSame('New Name', $client->fresh()->name);
    }

    public function test_super_admin_can_suspend_and_activate_a_client(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.clients.suspend', $client))->assertRedirect();
        $this->assertSame('suspended', $client->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.clients.activate', $client))->assertRedirect();
        $this->assertSame('active', $client->fresh()->status);
    }

    public function test_client_index_search_and_status_filter(): void
    {
        Client::factory()->create(['name' => 'Findable Co', 'status' => 'active']);
        Client::factory()->create(['name' => 'Other Co', 'status' => 'suspended']);

        $response = $this->actingAs($this->admin())->get(route('admin.clients.index', ['search' => 'Findable']));

        $response->assertOk()->assertSee('Findable Co')->assertDontSee('Other Co');
    }

    public function test_client_side_role_cannot_reach_client_management_routes(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();
        $user = User::factory()->create(['is_super_admin' => false]);
        $user->roles()->attach($role);

        $client = Client::factory()->create();

        // Blocked twice over: the `super-admin` area middleware, and (even if
        // that were bypassed) ClientPolicy — clients.* was never granted to
        // Client Owner (see RolePermissionSeeder).
        $this->actingAs($user)->get(route('admin.clients.index'))->assertForbidden();
        $this->assertFalse($user->hasPermission('clients.view'));
    }

    public function test_business_slug_is_unique_per_client_not_globally(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        Business::factory()->create(['client_id' => $clientA->id, 'name' => 'Main Store', 'slug' => 'main-store']);

        // Same business name, different client — should not collide.
        $admin = $this->admin();
        $businessType = \App\Models\BusinessType::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.businesses.store'), [
            'client_id' => $clientB->id,
            'business_type_id' => $businessType->id,
            'name' => 'Main Store',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('businesses', [
            'client_id' => $clientB->id,
            'name' => 'Main Store',
            'slug' => 'main-store',
        ]);
    }
}
