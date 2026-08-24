<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => ['status' => 'ok'],
        ]);
    }

    public function test_login_issues_a_token_and_it_authorizes_subsequent_requests(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password-123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password-123!',
            'device_name' => 'phpunit-test',
        ]);

        $response->assertOk()->assertJsonStructure([
            'success', 'message',
            'data' => ['token', 'user' => ['id', 'name', 'email']],
        ]);
        $this->assertTrue($response->json('success'));
        $token = $response->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_rejects_invalid_credentials_without_revealing_which_field_was_wrong(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password-123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'phpunit-test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertFalse($response->json('success'));
    }

    public function test_protected_endpoints_reject_requests_with_no_token(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->getJson('/api/v1/config')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('test')->plainTextToken;
        $tokenId = explode('|', $plainTextToken, 2)[0];

        $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        // Asserted against the database directly rather than via a second
        // simulated request: Sanctum's guard caches the resolved user
        // in-memory for the lifetime of the test's single Application
        // instance, so a second `$this->getJson()` in the same test method
        // would incorrectly see the pre-logout cached user regardless of
        // whether the token row was actually deleted — a test-harness
        // artifact of reusing one process for two "requests", not a real
        // gap (a genuine second HTTP request re-resolves the guard fresh).
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_config_endpoint_returns_only_the_authenticated_users_own_business_data(): void
    {
        $pos = Feature::factory()->create(['slug' => 'pos']);
        $package = Package::factory()->create();
        $package->features()->attach($pos);

        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id, 'currency' => 'PKR']);
        $user = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonPath('data.business.id', $business->id)
            ->assertJsonPath('data.business.currency', 'PKR')
            ->assertJsonFragment(['features' => ['pos']]);
    }

    public function test_config_endpoint_includes_the_callers_own_branch_permission_free(): void
    {
        // A branch-pinned Cashier has no `businesses.view` permission (see
        // RolePermissionSeeder), so GET /branches 403s for them — this is
        // the one place they can still learn their own branch's name,
        // found missing via live Cashier testing, not merely assumed.
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id, 'name' => 'Downtown', 'is_main' => false]);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $token = $cashier->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.branch.name', 'Downtown')
            ->assertJsonPath('data.branch.is_main', false);
    }

    public function test_config_endpoint_branch_is_null_for_an_actor_with_no_fixed_branch(): void
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => null]);
        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/config');

        $response->assertOk()->assertJsonPath('data.branch', null);
    }

    public function test_config_endpoint_cannot_be_redirected_to_another_business_via_a_parameter(): void
    {
        // There is no {business} route parameter on this endpoint at all —
        // it always resolves strictly from the authenticated token's own
        // user record, so there is no id here for a client to tamper with.
        $otherBusiness = Business::factory()->create();
        $user = User::factory()->create(); // no business of their own
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/config?business_id='.$otherBusiness->id);

        $response->assertOk()->assertJsonPath('data.business', null);
    }
}
