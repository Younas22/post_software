<?php

namespace Tests\Feature\Api\V1;

use App\Models\Business;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsApi(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken);
    }

    /**
     * @return array{0: Client, 1: Business, 2: User}
     */
    private function ownerSetup(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$client, $business, $owner];
    }

    private function customer(Business $business, array $overrides = []): Customer
    {
        $customer = new Customer();
        $customer->business_id = $business->id;
        $customer->fill(array_merge(['name' => 'Customer', 'status' => 'active'], $overrides));
        $customer->save();

        return $customer;
    }

    private function supplier(Business $business, array $overrides = []): Supplier
    {
        $supplier = new Supplier();
        $supplier->business_id = $business->id;
        $supplier->fill(array_merge(['name' => 'Supplier', 'status' => 'active'], $overrides));
        $supplier->save();

        return $supplier;
    }

    // --- Customers ---

    public function test_client_owner_can_create_a_customer(): void
    {
        [, $business, $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/customers', [
            'name' => 'Jane Buyer', 'phone' => '555-1234', 'status' => 'active',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Jane Buyer');
        $this->assertDatabaseHas('customers', ['business_id' => $business->id, 'name' => 'Jane Buyer']);
    }

    public function test_creating_a_customer_ignores_a_spoofed_business_id(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/customers', [
            'business_id' => $otherBusiness->id,
            'name' => 'Sneaky', 'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertSame($business->id, Customer::where('name', 'Sneaky')->first()->business_id);
    }

    public function test_cannot_view_or_update_another_tenants_customer(): void
    {
        [, , $owner] = $this->ownerSetup();
        $foreign = $this->customer(Business::factory()->create(), ['name' => 'Foreign']);

        $this->actingAsApi($owner)->getJson("/api/v1/customers/{$foreign->id}")->assertStatus(403);
        $this->actingAsApi($owner)->patchJson("/api/v1/customers/{$foreign->id}", ['name' => 'Hijack'])->assertStatus(403);
    }

    public function test_customers_list_supports_search_and_is_tenant_scoped(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $this->customer($business, ['name' => 'Findable Person']);
        $this->customer(Business::factory()->create(), ['name' => 'Other Tenant Customer']);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/customers?search=Findable');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_deleting_a_customer_soft_deletes_it(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $customer = $this->customer($business, ['name' => 'Removable']);

        $this->actingAsApi($owner)->deleteJson("/api/v1/customers/{$customer->id}")->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_validation_requires_name_and_status(): void
    {
        [, , $owner] = $this->ownerSetup();

        $this->actingAsApi($owner)->postJson('/api/v1/customers', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'status']);
    }

    // --- Suppliers ---

    public function test_client_owner_can_create_a_supplier(): void
    {
        [, $business, $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/suppliers', [
            'name' => 'Acme Distribution', 'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('suppliers', ['business_id' => $business->id, 'name' => 'Acme Distribution']);
    }

    public function test_cannot_view_another_tenants_supplier(): void
    {
        [, , $owner] = $this->ownerSetup();
        $foreign = $this->supplier(Business::factory()->create(), ['name' => 'Foreign Supplier']);

        $this->actingAsApi($owner)->getJson("/api/v1/suppliers/{$foreign->id}")->assertStatus(403);
    }

    // --- Permissions ---

    public function test_cashier_can_view_and_create_but_not_delete_customers(): void
    {
        [$client, $business, ] = $this->ownerSetup();
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/customers')->assertOk();
        $this->actingAsApi($cashier)->postJson('/api/v1/customers', ['name' => 'Walk-in', 'status' => 'active'])->assertCreated();

        $customer = Customer::where('name', 'Walk-in')->firstOrFail();
        $this->actingAsApi($cashier)->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
    }

    public function test_cashier_cannot_manage_suppliers(): void
    {
        [$client, $business, ] = $this->ownerSetup();
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/suppliers')->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->getJson('/api/v1/suppliers')->assertUnauthorized();
    }
}
