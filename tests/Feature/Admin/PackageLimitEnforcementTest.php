<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PackageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function clientWithLimits(?int $maxBranches, ?int $maxUsers): Client
    {
        $client = Client::factory()->create();
        $package = Package::factory()->create(['max_branches' => $maxBranches, 'max_users' => $maxUsers]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);

        return $client;
    }

    // --- Branch limit ---

    public function test_branch_creation_allowed_below_limit(): void
    {
        $client = $this->clientWithLimits(maxBranches: 2, maxUsers: null);
        $business = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->create(['business_id' => $business->id]); // 1 of 2

        $limits = app(PackageLimitService::class);
        $this->assertTrue($limits->canCreateBranch($client));

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Second Branch',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertDatabaseHas('branches', ['business_id' => $business->id, 'name' => 'Second Branch']);
    }

    public function test_branch_creation_rejected_at_exact_limit(): void
    {
        $client = $this->clientWithLimits(maxBranches: 2, maxUsers: null);
        $business = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->count(2)->create(['business_id' => $business->id]); // exactly at limit

        $limits = app(PackageLimitService::class);
        $this->assertFalse($limits->canCreateBranch($client));

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.branches.store', $business), [
            'name' => 'One Too Many',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('branch');
        $this->assertDatabaseMissing('branches', ['name' => 'One Too Many']);
    }

    public function test_branch_creation_rejected_above_limit_across_multiple_businesses(): void
    {
        // max_branches is client-wide, not per-business.
        $client = $this->clientWithLimits(maxBranches: 1, maxUsers: null);
        $businessA = Business::factory()->create(['client_id' => $client->id]);
        $businessB = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->create(['business_id' => $businessA->id]); // already at limit via a DIFFERENT business

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.branches.store', $businessB), [
            'name' => 'Branch On B',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('branch');
    }

    public function test_branch_creation_unlimited_when_package_has_no_cap(): void
    {
        $client = $this->clientWithLimits(maxBranches: null, maxUsers: null);
        $business = Business::factory()->create(['client_id' => $client->id]);
        Branch::factory()->count(50)->create(['business_id' => $business->id]);

        $this->assertTrue(app(PackageLimitService::class)->canCreateBranch($client));
    }

    // --- User limit (service-level only — no employee endpoint yet) ---

    public function test_user_limit_allows_below_limit(): void
    {
        $client = $this->clientWithLimits(maxBranches: null, maxUsers: 3);
        User::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'active']);

        $this->assertTrue(app(PackageLimitService::class)->canCreateUser($client));
    }

    public function test_user_limit_rejects_at_exact_limit(): void
    {
        $client = $this->clientWithLimits(maxBranches: null, maxUsers: 3);
        User::factory()->count(3)->create(['client_id' => $client->id, 'status' => 'active']);

        $this->assertFalse(app(PackageLimitService::class)->canCreateUser($client));
    }

    public function test_user_limit_rejects_above_limit(): void
    {
        $client = $this->clientWithLimits(maxBranches: null, maxUsers: 2);
        User::factory()->count(5)->create(['client_id' => $client->id, 'status' => 'active']);

        $this->assertFalse(app(PackageLimitService::class)->canCreateUser($client));
    }

    public function test_user_limit_does_not_count_inactive_or_soft_deleted_users(): void
    {
        $client = $this->clientWithLimits(maxBranches: null, maxUsers: 2);
        User::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        User::factory()->create(['client_id' => $client->id, 'status' => 'inactive']); // deactivated — frees a seat
        $deleted = User::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $deleted->delete(); // soft-deleted — frees a seat

        $limits = app(PackageLimitService::class);
        $this->assertSame(1, $limits->userCount($client));
        $this->assertTrue($limits->canCreateUser($client)); // 1 active of 2 allowed
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'password' => bcrypt('Password-123!')]);

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'Password-123!']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    // --- Branch activation/deactivation ---

    public function test_super_admin_can_activate_and_deactivate_a_branch(): void
    {
        $branch = Branch::factory()->create(['status' => 'active']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.businesses.branches.deactivate', [$branch->business, $branch]))->assertRedirect();
        $this->assertSame('inactive', $branch->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.businesses.branches.activate', [$branch->business, $branch]))->assertRedirect();
        $this->assertSame('active', $branch->fresh()->status);
    }

    public function test_branch_activation_is_logged(): void
    {
        $branch = Branch::factory()->create(['status' => 'inactive']);

        $this->actingAs($this->admin())->patch(route('admin.businesses.branches.activate', [$branch->business, $branch]));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'branch.activated',
            'subject_id' => $branch->id,
            'subject_type' => Branch::class,
        ]);
    }

    public function test_client_owner_cannot_modify_another_tenants_branch(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->first() ?? Role::factory()->create(['slug' => 'client-owner']);

        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherBusiness = Business::factory()->create(['client_id' => $otherClient->id]);
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id, 'status' => 'active']);

        $user = User::factory()->create(['is_super_admin' => false, 'client_id' => $ownClient->id]);
        $user->roles()->attach($role);

        $this->assertFalse($user->can('update', $otherBranch));
    }

    public function test_deactivating_a_branch_requires_permission(): void
    {
        $branch = Branch::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['is_super_admin' => false]); // no role at all

        $response = $this->actingAs($user)->patch(route('admin.businesses.branches.deactivate', [$branch->business, $branch]));

        $response->assertForbidden();
        $this->assertSame('active', $branch->fresh()->status);
    }
}
