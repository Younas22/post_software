<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Device;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    /**
     * A real bearer token, not Sanctum::actingAs() — several tests here
     * issue a SEPARATE device token later in the same method and must
     * authenticate with it via the actual token-parsing path. actingAs()
     * calls the guard's setUser(), which then caches that user for the
     * rest of the test regardless of which Authorization header a later
     * request sends, so it can't be mixed with a real device-token check.
     */
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
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$client, $business, $branch, $owner];
    }

    private function activeSubscription(Client $client): void
    {
        $package = Package::factory()->create(['max_devices' => 5]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
    }

    /**
     * Builds a device + plaintext token directly, rather than via a live
     * POST /devices/register call — the 'sanctum' guard caches whichever
     * identity it resolves first for the life of a test method (see
     * Illuminate\Auth\RequestGuard::user()), so a test that already made
     * one authenticated request as a User cannot reliably make a second
     * one as a Device afterward in the same method. Building the device
     * directly keeps each test to exactly one auth resolution, and the
     * registration endpoint itself is already covered by the tests above.
     *
     * @return array{0: Device, 1: string}
     */
    private function registeredDevice(Branch $branch, array $overrides = []): array
    {
        $device = new Device();
        $device->client_id = $branch->business->client_id;
        $device->business_id = $branch->business_id;
        $device->branch_id = $branch->id;
        $device->fill(array_merge([
            'name' => 'Test Device', 'platform' => 'windows', 'device_identifier' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'active', 'activated_at' => now(),
        ], $overrides));
        $device->save();

        return [$device, $device->createToken($device->name)->plainTextToken];
    }

    // --- Registration ---

    public function test_an_authorized_user_can_register_a_new_device(): void
    {
        [$client, , $branch, $owner] = $this->ownerSetup();
        $this->activeSubscription($client);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/devices/register', [
            'branch_id' => $branch->id,
            'name' => 'Front Counter POS',
            'platform' => 'windows',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame('active', $response->json('data.device.status'));
        $this->assertNotNull($response->json('data.device.device_identifier'));
        $this->assertDatabaseHas('devices', ['branch_id' => $branch->id, 'name' => 'Front Counter POS', 'status' => 'active']);
    }

    public function test_the_device_identifier_is_server_generated_not_client_supplied(): void
    {
        [$client, , $branch, $owner] = $this->ownerSetup();
        $this->activeSubscription($client);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/devices/register', [
            'branch_id' => $branch->id,
            'name' => 'Spoofed Device',
            'platform' => 'windows',
            'device_identifier' => 'attacker-chosen-id', // not an accepted field at all
        ]);

        $response->assertCreated();
        $this->assertNotSame('attacker-chosen-id', $response->json('data.device.device_identifier'));
    }

    // --- Activation (immediate on registration) ---

    public function test_a_freshly_registered_device_is_immediately_active_with_a_working_token(): void
    {
        [, , $branch, ] = $this->ownerSetup();
        [, $token] = $this->registeredDevice($branch, ['status' => 'active']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/devices/current');

        $response->assertOk()->assertJsonPath('data.status', 'active');
    }

    // --- Blocked / deactivated device ---

    public function test_a_blocked_device_cannot_use_protected_device_endpoints(): void
    {
        [, , $branch, ] = $this->ownerSetup();
        [, $token] = $this->registeredDevice($branch, ['status' => 'blocked']);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/devices/current')->assertStatus(403);
    }

    public function test_a_deactivated_device_cannot_use_protected_device_endpoints(): void
    {
        [, , $branch, ] = $this->ownerSetup();
        [, $token] = $this->registeredDevice($branch, ['status' => 'inactive']);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/devices/current')->assertStatus(403);
    }

    public function test_a_user_token_cannot_be_used_against_a_device_only_endpoint(): void
    {
        [$client, , , $owner] = $this->ownerSetup();
        $this->activeSubscription($client);

        $this->actingAsApi($owner)->getJson('/api/v1/devices/current')->assertStatus(401);
    }

    // --- Wrong business ---

    public function test_cannot_register_a_device_for_another_tenants_branch(): void
    {
        [$client, , , $owner] = $this->ownerSetup();
        $this->activeSubscription($client);
        $otherBranch = Branch::factory()->create();

        $this->actingAsApi($owner)->postJson('/api/v1/devices/register', [
            'branch_id' => $otherBranch->id, 'name' => 'Sneaky', 'platform' => 'windows',
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    // --- Device limit ---

    public function test_registration_is_rejected_once_the_device_limit_is_reached(): void
    {
        [$client, , $branch, $owner] = $this->ownerSetup();
        $package = Package::factory()->create(['max_devices' => 1]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);

        $this->actingAsApi($owner)->postJson('/api/v1/devices/register', [
            'branch_id' => $branch->id, 'name' => 'First Device', 'platform' => 'windows',
        ])->assertCreated();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/devices/register', [
            'branch_id' => $branch->id, 'name' => 'One Too Many', 'platform' => 'windows',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('devices', ['name' => 'One Too Many']);
    }

    // --- Token validation ---

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')->getJson('/api/v1/devices/current')->assertUnauthorized();
    }

    public function test_registration_requires_permission(): void
    {
        [$client, $business, $branch, ] = $this->ownerSetup();
        $this->activeSubscription($client);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->postJson('/api/v1/devices/register', [
            'branch_id' => $branch->id, 'name' => 'Unauthorized Attempt', 'platform' => 'windows',
        ])->assertForbidden();
    }

    public function test_unauthenticated_registration_is_rejected(): void
    {
        $this->postJson('/api/v1/devices/register', [])->assertUnauthorized();
    }
}
