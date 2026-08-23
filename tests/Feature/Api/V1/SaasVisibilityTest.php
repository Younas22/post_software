<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the previously-missing token-authed endpoints added for the Web POS
 * frontend (see docs/LARAVEL_API_DISCOVERY.md §21): Branches, "my business"
 * profile, Subscription, Payment Methods, Dashboard, and self-service
 * account actions.
 */
class SaasVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class,
            FeatureSeeder::class, PackageSeeder::class, PaymentMethodSeeder::class,
        ]);
    }

    private function actingAsApi(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken);
    }

    /**
     * @return array{0: Client, 1: Business, 2: Branch, 3: User}
     */
    private function ownerSetup(): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id, 'is_main' => true]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$client, $business, $branch, $owner];
    }

    // --- Branches ---

    public function test_owner_can_list_and_create_branches(): void
    {
        [$client, , , $owner] = $this->ownerSetup();
        // The "starter" package (used by ownerSetup) allows only 1 branch,
        // which the setup's main branch already consumes — swap to
        // "business" (max_branches: 3) so this test exercises a genuine
        // create, not the limit-enforcement path (see the dedicated
        // package-limit test below for that).
        $businessPackage = Package::query()->where('slug', 'business')->firstOrFail();
        $client->currentSubscription->update(['package_id' => $businessPackage->id]);

        $this->actingAsApi($owner)->getJson('/api/v1/branches')->assertOk();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/branches', [
            'name' => 'Second Branch', 'status' => 'active',
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Second Branch');
    }

    public function test_creating_a_branch_beyond_the_package_limit_is_rejected(): void
    {
        [, , , $owner] = $this->ownerSetup();
        // ownerSetup's "starter" package allows only 1 branch, already
        // consumed by the setup's own main branch.
        $response = $this->actingAsApi($owner)->postJson('/api/v1/branches', [
            'name' => 'Second Branch', 'status' => 'active',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', "This client's package allows at most 1 branch(es). Upgrade the package to add another.");
    }

    public function test_branches_are_tenant_scoped(): void
    {
        [, , , $owner] = $this->ownerSetup();
        $foreignBranch = Branch::factory()->create();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/branches');

        $response->assertOk();
        $this->assertFalse(collect($response->json('data'))->contains('id', $foreignBranch->id));
        $this->actingAsApi($owner)->getJson("/api/v1/branches/{$foreignBranch->id}")->assertStatus(403);
    }

    public function test_cashier_cannot_manage_branches(): void
    {
        [$client, $business, , ] = $this->ownerSetup();
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/branches')->assertForbidden();
        $this->actingAsApi($cashier)->postJson('/api/v1/branches', ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }

    // --- Business profile ---

    public function test_owner_can_view_and_update_own_business(): void
    {
        [, $business, , $owner] = $this->ownerSetup();

        $this->actingAsApi($owner)->getJson('/api/v1/business')->assertOk()->assertJsonPath('data.id', $business->id);

        $response = $this->actingAsApi($owner)->putJson('/api/v1/business', [
            'name' => 'Renamed Business', 'currency' => 'USD', 'timezone' => 'UTC',
        ]);
        $response->assertOk()->assertJsonPath('data.name', 'Renamed Business');
    }

    // --- Subscription ---

    public function test_owner_can_view_own_subscription_and_usage(): void
    {
        [, , , $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/subscription');

        $response->assertOk();
        $this->assertEquals('trial', $response->json('data.subscription.effective_status'));
        $this->assertEquals('starter', $response->json('data.package.slug'));
        $this->assertIsInt($response->json('data.usage.users.used'));
    }

    // --- Payment methods ---

    public function test_payment_methods_include_global_defaults(): void
    {
        [, , , $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/payment-methods');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->contains('slug', 'cash'));
    }

    // --- Dashboard ---

    public function test_dashboard_returns_zeros_for_a_business_with_no_sales_yet(): void
    {
        [, , , $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.today.orders_count'));
        $this->assertEquals(0, $response->json('data.low_stock_count'));
        $this->assertEquals([], $response->json('data.recent_sales'));
    }

    // --- Account self-service ---

    public function test_user_can_change_own_password(): void
    {
        [, , , $owner] = $this->ownerSetup();
        $owner->update(['password' => 'old-password-123']);

        $response = $this->actingAsApi($owner)->putJson('/api/v1/user/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-456', $owner->fresh()->password));
    }

    public function test_changing_password_requires_correct_current_password(): void
    {
        [, , , $owner] = $this->ownerSetup();
        $owner->update(['password' => 'old-password-123']);

        $this->actingAsApi($owner)->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/branches')->assertUnauthorized();
        $this->getJson('/api/v1/business')->assertUnauthorized();
        $this->getJson('/api/v1/subscription')->assertUnauthorized();
        $this->getJson('/api/v1/payment-methods')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}
