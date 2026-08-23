<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function actingAsApi(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user));
    }

    /**
     * @return array{0: Client, 1: Business, 2: Branch, 3: User} [client, business, branch, owner]
     */
    private function clientOwnerSetup(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);

        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$client, $business, $branch, $owner];
    }

    // --- Create ---

    public function test_client_owner_can_create_an_employee(): void
    {
        [$client, $business, $branch, $owner] = $this->clientOwnerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'New Cashier',
            'email' => 'cashier@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
            'business_id' => $business->id,
            'branch_id' => $branch->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'cashier@example.com');
        $employee = User::query()->where('email', 'cashier@example.com')->firstOrFail();
        $this->assertSame($client->id, $employee->client_id);
        $this->assertSame($business->id, $employee->business_id);
        $this->assertTrue($employee->hasRole('cashier'));
    }

    public function test_super_admin_can_create_an_employee_for_any_client_by_specifying_client_id(): void
    {
        $client = Client::factory()->create();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAsApi($admin)->postJson('/api/v1/employees', [
            'name' => 'Provisioned Owner Staff',
            'email' => 'staff@example.com',
            'password' => 'Password-123!',
            'role' => 'manager',
            'client_id' => $client->id,
        ]);

        $response->assertCreated();
        $this->assertSame($client->id, User::where('email', 'staff@example.com')->first()->client_id);
    }

    public function test_creating_an_employee_ignores_a_spoofed_client_id_for_non_super_admin(): void
    {
        [$ownClient, , , $owner] = $this->clientOwnerSetup();
        $otherClient = Client::factory()->create();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
            'client_id' => $otherClient->id, // attempted escalation
        ]);

        $response->assertCreated();
        $this->assertSame($ownClient->id, User::where('email', 'sneaky@example.com')->first()->client_id);
    }

    public function test_cannot_assign_super_admin_or_client_owner_role_via_employee_creation(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();

        foreach (['super-admin', 'client-owner'] as $forbiddenRole) {
            $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
                'name' => 'Escalator',
                'email' => "escalate-{$forbiddenRole}@example.com",
                'password' => 'Password-123!',
                'role' => $forbiddenRole,
            ]);

            $response->assertStatus(422)->assertJsonValidationErrors('role');
        }
    }

    public function test_is_super_admin_flag_cannot_be_set_via_employee_creation(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'Not An Admin',
            'email' => 'notanadmin@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
            'is_super_admin' => true, // not even a recognized field
        ]);

        $response->assertCreated();
        $this->assertFalse(User::where('email', 'notanadmin@example.com')->first()->is_super_admin);
    }

    public function test_cannot_assign_another_tenants_branch(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();
        $otherBusiness = Business::factory()->create();
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id]);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'Cross Tenant',
            'email' => 'crosstenant@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
            'branch_id' => $otherBranch->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_validation_rejects_duplicate_email_and_weak_password(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();
        $existing = User::factory()->create();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'Dup',
            'email' => $existing->email,
            'password' => 'weak',
            'role' => 'cashier',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    // --- Package user limit ---

    public function test_employee_creation_is_rejected_at_the_user_limit(): void
    {
        $client = Client::factory()->create();
        $package = Package::factory()->create(['max_users' => 1]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $owner = User::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());
        // $owner already occupies the one available seat.

        $response = $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'One Too Many',
            'email' => 'toomany@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertDatabaseMissing('users', ['email' => 'toomany@example.com']);
    }

    // --- List / show ---

    public function test_list_employees_is_scoped_to_the_actors_own_client_and_supports_search(): void
    {
        [$client, , , $owner] = $this->clientOwnerSetup();
        $ownColleague = User::factory()->create(['client_id' => $client->id, 'name' => 'Findable Colleague']);
        User::factory()->create(); // another client entirely

        $response = $this->actingAsApi($owner)->getJson('/api/v1/employees?search=Findable');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains($ownColleague->email, $emails);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_cannot_view_another_tenants_employee(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();
        $otherEmployee = User::factory()->create();

        $response = $this->actingAsApi($owner)->getJson("/api/v1/employees/{$otherEmployee->id}");

        $response->assertStatus(403);
    }

    // --- Update / activate / deactivate ---

    public function test_client_owner_can_update_and_reassign_role_of_their_own_employee(): void
    {
        [$client, $business, , $owner] = $this->clientOwnerSetup();
        $employee = User::factory()->create(['client_id' => $client->id]);
        $employee->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $response = $this->actingAsApi($owner)->patchJson("/api/v1/employees/{$employee->id}", [
            'name' => 'Promoted',
            'role' => 'manager',
            'business_id' => $business->id,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Promoted');
        $employee->refresh();
        $this->assertTrue($employee->hasRole('manager'));
        $this->assertFalse($employee->hasRole('cashier'));
        $this->assertSame($business->id, $employee->business_id);
    }

    public function test_cannot_update_another_tenants_employee(): void
    {
        [, , , $owner] = $this->clientOwnerSetup();
        $otherEmployee = User::factory()->create();

        $response = $this->actingAsApi($owner)->patchJson("/api/v1/employees/{$otherEmployee->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(403);
        $this->assertNotSame('Hijacked', $otherEmployee->fresh()->name);
    }

    public function test_activate_and_deactivate_employee(): void
    {
        [$client, , , $owner] = $this->clientOwnerSetup();
        $employee = User::factory()->create(['client_id' => $client->id, 'status' => 'active']);

        $this->actingAsApi($owner)->patchJson("/api/v1/employees/{$employee->id}/deactivate")->assertOk();
        $this->assertSame('inactive', $employee->fresh()->status);

        $this->actingAsApi($owner)->patchJson("/api/v1/employees/{$employee->id}/activate")->assertOk();
        $this->assertSame('active', $employee->fresh()->status);
    }

    public function test_activity_log_records_employee_lifecycle(): void
    {
        [$client, , , $owner] = $this->clientOwnerSetup();

        $this->actingAsApi($owner)->postJson('/api/v1/employees', [
            'name' => 'Logged Employee',
            'email' => 'logged@example.com',
            'password' => 'Password-123!',
            'role' => 'cashier',
        ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'employee.created', 'client_id' => $client->id]);
    }

    // --- Unauthorized access ---

    public function test_a_user_without_the_permission_cannot_manage_employees(): void
    {
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail()); // no users.* permission

        $this->actingAsApi($cashier)->getJson('/api/v1/employees')->assertForbidden();
        $this->actingAsApi($cashier)->postJson('/api/v1/employees', [])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
        $this->postJson('/api/v1/employees', [])->assertUnauthorized();
    }
}
