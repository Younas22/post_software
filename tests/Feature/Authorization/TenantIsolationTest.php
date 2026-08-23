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

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    /**
     * The core tenant-isolation guarantee: Client A must never be able to
     * reach Client B's data, even by guessing a valid business id in the URL.
     */
    public function test_client_a_cannot_view_client_b_business(): void
    {
        $clientOwnerRole = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $clientA->id, 'status' => 'active']);

        $businessA = Business::factory()->create(['client_id' => $clientA->id]);
        $businessB = Business::factory()->create(['client_id' => $clientB->id]);
        Branch::factory()->create(['business_id' => $businessA->id]);
        Branch::factory()->create(['business_id' => $businessB->id]);

        $userA = User::factory()->create(['is_super_admin' => false, 'client_id' => $clientA->id]);
        $userA->roles()->attach($clientOwnerRole);

        // Own business: allowed.
        $this->actingAs($userA)
            ->get(route('client.businesses.show', $businessA))
            ->assertOk();

        // Someone else's business, guessed by id: forbidden — the policy
        // compares $business->client_id against the AUTHENTICATED user's
        // client_id, never a value taken from the request.
        $this->actingAs($userA)
            ->get(route('client.businesses.show', $businessB))
            ->assertForbidden();
    }

    public function test_tampering_with_client_id_in_request_does_not_grant_access(): void
    {
        $clientOwnerRole = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $clientA->id, 'status' => 'active']);
        $businessB = Business::factory()->create(['client_id' => $clientB->id]);
        Branch::factory()->create(['business_id' => $businessB->id]);

        $userA = User::factory()->create(['is_super_admin' => false, 'client_id' => $clientA->id]);
        $userA->roles()->attach($clientOwnerRole);

        // Even if a malicious client POSTs a spoofed client_id claiming to be
        // clientB, tenant context comes from auth()->user()->client_id — this
        // request body value is simply not read by the policy at all.
        $this->actingAs($userA)
            ->get(route('client.businesses.show', $businessB).'?client_id='.$clientB->id)
            ->assertForbidden();
    }

    public function test_business_policy_grants_super_admin_access_to_any_client(): void
    {
        // Verified at the policy level, not via the /client route: Super
        // Admin never reaches that route at all (blocked by the client-user
        // area middleware first — see test_..._area tests), but the policy's
        // own bypass must still exist for future Super-Admin-side routes.
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->assertTrue($admin->can('view', $business));
    }
}
