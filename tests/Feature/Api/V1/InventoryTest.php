<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
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

    private function product(Business $business, array $overrides = []): Product
    {
        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(array_merge([
            'name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 10,
            'minimum_stock' => 5, 'status' => 'active',
        ], $overrides));
        $product->save();

        return $product;
    }

    // --- StockService (unit-level) ---

    public function test_adjust_increases_stock_and_records_a_movement(): void
    {
        [, $business, $branch, ] = $this->ownerSetup();
        $product = $this->product($business);

        $inventory = app(StockService::class)->adjust($branch, $product, 20, 'purchase');

        $this->assertSame(20, $inventory->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id, 'product_id' => $product->id, 'type' => 'purchase', 'quantity' => 20, 'balance_after' => 20,
        ]);
    }

    public function test_adjust_decreases_stock_correctly_across_multiple_calls(): void
    {
        [, $business, $branch, ] = $this->ownerSetup();
        $product = $this->product($business);
        $service = app(StockService::class);

        $service->adjust($branch, $product, 10, 'purchase');
        $inventory = $service->adjust($branch, $product, -3, 'sale');

        $this->assertSame(7, $inventory->quantity);
    }

    public function test_adjust_rejects_a_decrease_that_would_go_negative(): void
    {
        [, $business, $branch, ] = $this->ownerSetup();
        $product = $this->product($business);
        $service = app(StockService::class);
        $service->adjust($branch, $product, 5, 'purchase');

        $this->expectException(InsufficientStockException::class);
        $service->adjust($branch, $product, -10, 'sale');
    }

    public function test_a_third_sequential_sale_fails_once_stock_is_exhausted(): void
    {
        [, $business, $branch, ] = $this->ownerSetup();
        $product = $this->product($business);
        $service = app(StockService::class);
        $service->adjust($branch, $product, 2, 'purchase');

        $service->adjust($branch, $product, -1, 'sale');
        $service->adjust($branch, $product, -1, 'sale');

        $this->expectException(InsufficientStockException::class);
        $service->adjust($branch, $product, -1, 'sale');
    }

    // --- API ---

    public function test_manual_adjustment_via_api_updates_inventory(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/inventory/adjust', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'notes' => 'Opening stock',
        ]);

        $response->assertOk()->assertJsonPath('data.quantity', 15);
    }

    public function test_adjustment_below_zero_returns_409_via_api(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/inventory/adjust', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => -5,
        ]);

        $response->assertStatus(409);
        $this->assertFalse($response->json('success'));
    }

    public function test_cannot_adjust_stock_for_another_tenants_branch(): void
    {
        [, , , $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id]);
        $otherProduct = $this->product($otherBusiness);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/inventory/adjust', [
            'branch_id' => $otherBranch->id,
            'product_id' => $otherProduct->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_branch_scoped_user_cannot_adjust_a_different_branch_in_the_same_business(): void
    {
        [$client, $business, $branch, ] = $this->ownerSetup();
        $otherBranch = Branch::factory()->create(['business_id' => $business->id]);
        $product = $this->product($business);

        $manager = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $manager->roles()->attach(Role::query()->where('slug', 'manager')->firstOrFail());

        $response = $this->actingAsApi($manager)->postJson('/api/v1/inventory/adjust', [
            'branch_id' => $otherBranch->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_low_stock_endpoint_lists_only_items_at_or_below_minimum(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $low = $this->product($business, ['minimum_stock' => 10]);
        $healthy = $this->product($business, ['minimum_stock' => 5]);
        $service = app(StockService::class);
        $service->adjust($branch, $low, 3, 'purchase');
        $service->adjust($branch, $healthy, 50, 'purchase');

        $response = $this->actingAsApi($owner)->getJson('/api/v1/inventory/low-stock');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('product_id');
        $this->assertContains($low->id, $ids);
        $this->assertNotContains($healthy->id, $ids);
    }

    public function test_movement_history_is_recorded_and_retrievable(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $product = $this->product($business);
        $service = app(StockService::class);
        $service->adjust($branch, $product, 10, 'purchase');
        $service->adjust($branch, $product, -4, 'sale');

        $response = $this->actingAsApi($owner)->getJson("/api/v1/products/{$product->id}/movements");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_a_user_without_permission_cannot_adjust_stock(): void
    {
        [$client, $business, $branch, ] = $this->ownerSetup();
        $product = $this->product($business);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->postJson('/api/v1/inventory/adjust', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/inventory')->assertUnauthorized();
        $this->postJson('/api/v1/inventory/adjust', [])->assertUnauthorized();
    }
}
