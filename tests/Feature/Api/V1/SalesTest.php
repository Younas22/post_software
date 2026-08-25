<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
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
     * A client with an active POS-enabled subscription, one business, one
     * branch, a cashier, a stocked product, and a cash payment method.
     *
     * @return array{0: Business, 1: Branch, 2: User, 3: Product}
     */
    private function posReadySetup(int $stock = 10): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);

        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'tax_rate' => 10, 'status' => 'active']);
        $product->save();

        app(StockService::class)->adjust($branch, $product, $stock, 'adjustment');

        return [$business, $branch, $cashier, $product];
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    }

    private function saleBody(Branch $branch, Product $product, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'client_transaction_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['payment_method_id' => $this->cashMethod()->id, 'amount' => 44]],
        ], $overrides);
    }

    // --- Complete sale ---

    public function test_a_complete_sale_computes_totals_deducts_stock_and_returns_a_receipt(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup();

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product));

        $response->assertCreated();
        // 2 * 20 = 40 subtotal, 10% tax = 4 -> total 44
        $this->assertEquals(40, $response->json('data.subtotal'));
        $this->assertEquals(44, $response->json('data.total'));
        $this->assertSame('Widget', $response->json('data.items.0.product_name'));
        $this->assertSame(8, app(StockService::class)->currentQuantity($branch, $product));
        $this->assertDatabaseHas('sales', ['branch_id' => $branch->id, 'status' => 'completed']);
    }

    // --- Insufficient stock ---

    public function test_insufficient_stock_rejects_the_sale_and_leaves_no_trace(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup(stock: 1);

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product, [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]));

        $response->assertStatus(409);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(1, app(StockService::class)->currentQuantity($branch, $product));
    }

    // --- Wrong price ---

    public function test_a_client_supplied_price_is_ignored_in_favor_of_the_authoritative_product_price(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup();

        $body = $this->saleBody($branch, $product);
        $body['items'][0]['unit_price'] = 1; // attempted spoof; not an accepted field at all
        $body['items'][0]['price'] = 1;

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $body);

        $response->assertCreated();
        $this->assertEquals(20, $response->json('data.items.0.unit_price'));
        $this->assertEquals(40, $response->json('data.subtotal'));
    }

    // --- Wrong tenant / wrong branch ---

    public function test_wrong_tenant_branch_is_rejected(): void
    {
        [, , $cashier, $product] = $this->posReadySetup();
        $otherBranch = Branch::factory()->create();

        $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($otherBranch, $product))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_wrong_branch_within_the_same_business_is_rejected_for_a_branch_scoped_cashier(): void
    {
        [$business, $branch, $cashier, $product] = $this->posReadySetup();
        $otherBranch = Branch::factory()->create(['business_id' => $business->id]);

        $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($otherBranch, $product))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    // --- Duplicate transaction (idempotency) ---

    public function test_a_duplicate_client_transaction_id_does_not_create_a_second_sale(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup();
        $body = $this->saleBody($branch, $product);

        $first = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $body);
        $second = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $body);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.invoice_number'), $second->json('data.invoice_number'));
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(8, app(StockService::class)->currentQuantity($branch, $product));
    }

    // --- Rollback ---

    public function test_an_invalid_line_item_rolls_back_the_entire_sale(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup();
        $foreignProduct = (function () {
            $p = new Product();
            $p->business_id = Business::factory()->create()->id;
            $p->fill(['name' => 'Foreign', 'sku' => 'FOR-'.uniqid(), 'purchase_price' => 1, 'sale_price' => 1, 'status' => 'active']);
            $p->save();

            return $p;
        })();

        $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $foreignProduct->id, 'quantity' => 1],
            ],
        ]))->assertStatus(422);

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, app(StockService::class)->currentQuantity($branch, $product));
    }

    // --- Concurrent stock sale (simulated: two sequential sales exhausting stock) ---

    public function test_a_second_sale_fails_cleanly_once_stock_is_exhausted_by_the_first(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup(stock: 2);

        $first = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product, [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));
        $first->assertCreated();

        $second = $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product, [
            'client_transaction_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $second->assertStatus(409);

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(0, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_service_level_concurrent_calls_never_oversell_the_last_unit(): void
    {
        [, $branch, $cashier, $product] = $this->posReadySetup(stock: 1);
        $service = app(SaleService::class);
        $method = $this->cashMethod();

        $one = $service->create($branch, $cashier, null, [['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $method->id, 'amount' => 22]], 'concurrent-a', ['a' => 1]);
        $this->assertNotEmpty($one);

        $this->expectException(InsufficientStockException::class);
        $service->create($branch, $cashier, null, [['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $method->id, 'amount' => 22]], 'concurrent-b', ['a' => 2]);
    }

    // --- Permissions ---

    public function test_a_user_without_the_sales_permission_cannot_create_a_sale(): void
    {
        [$business, $branch, , $product] = $this->posReadySetup();
        $client = $business->client;
        $noPerm = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        // Deliberately no role attached -> no permissions at all.

        $this->actingAsApi($noPerm)->postJson('/api/v1/sales', $this->saleBody($branch, $product))->assertForbidden();
    }

    // --- Subscription ---

    public function test_a_sale_is_rejected_when_the_clients_subscription_is_not_active(): void
    {
        [$business, $branch, $cashier, $product] = $this->posReadySetup();
        $business->client->currentSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product))
            ->assertStatus(403);
    }

    // --- Feature access ---

    public function test_a_sale_is_rejected_when_the_pos_feature_is_disabled_for_the_business(): void
    {
        [$business, $branch, $cashier, $product] = $this->posReadySetup();
        $business->featureOverrides()->attach(
            \App\Models\Feature::query()->where('slug', 'pos')->firstOrFail()->id,
            ['enabled' => false],
        );

        $this->actingAsApi($cashier)->postJson('/api/v1/sales', $this->saleBody($branch, $product))
            ->assertStatus(403);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/sales')->assertUnauthorized();
        $this->postJson('/api/v1/sales', [])->assertUnauthorized();
    }

    // --- Services (non-inventory items) ---

    private function makeService(Business $business, string $name = 'Consulting', float $price = 50): Product
    {
        $service = new Product();
        $service->business_id = $business->id;
        $service->fill([
            'name' => $name, 'sku' => 'SVC-'.uniqid(), 'type' => 'service',
            'purchase_price' => 0, 'sale_price' => $price, 'tax_rate' => 10, 'status' => 'active',
        ]);
        $service->save();

        return $service;
    }

    public function test_a_service_can_be_sold_with_zero_stock_and_never_creates_an_inventory_row(): void
    {
        [$business, $branch, $cashier] = $this->posReadySetup();
        $service = $this->makeService($business);

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/sales', [
            'branch_id' => $branch->id,
            'client_transaction_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [['product_id' => $service->id, 'quantity' => 3]],
            'payments' => [['payment_method_id' => $this->cashMethod()->id, 'amount' => 165]],
        ]);

        // 3 * 50 = 150 subtotal, 10% tax = 15 -> total 165. Would have thrown
        // InsufficientStockException before the isService() guard, since a
        // service never has an inventories row (currentQuantity = 0).
        $response->assertCreated();
        $this->assertEquals(150, $response->json('data.subtotal'));
        $this->assertEquals(165, $response->json('data.total'));
        $this->assertDatabaseMissing('inventories', ['product_id' => $service->id]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $service->id]);
    }

    public function test_a_mixed_sale_deducts_stock_only_for_the_physical_product_line(): void
    {
        [$business, $branch, $cashier, $product] = $this->posReadySetup(stock: 5);
        $service = $this->makeService($business);

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/sales', [
            'branch_id' => $branch->id,
            'client_transaction_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $service->id, 'quantity' => 1],
            ],
            'payments' => [['payment_method_id' => $this->cashMethod()->id, 'amount' => 1000]],
        ]);

        $response->assertCreated();
        $this->assertSame(3, app(StockService::class)->currentQuantity($branch, $product), 'physical product stock must be deducted');
        $this->assertDatabaseMissing('inventories', ['product_id' => $service->id]);
        $this->assertDatabaseHas('sale_items', ['product_id' => $service->id, 'quantity' => 1]);
        $this->assertDatabaseHas('sale_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_a_service_is_created_and_listed_via_the_products_api_with_the_type_filter(): void
    {
        [$business, , $cashier] = $this->posReadySetup();
        $cashier->roles()->attach(Role::query()->where('slug', 'business-admin')->firstOrFail());

        $create = $this->actingAsApi($cashier)->postJson('/api/v1/products', [
            'business_id' => $business->id,
            'name' => 'Delivery Fee',
            'sku' => 'SVC-DELIVERY',
            'type' => 'service',
            'purchase_price' => 0,
            'sale_price' => 5,
            'status' => 'active',
        ]);
        $create->assertCreated();
        $this->assertSame('service', $create->json('data.type'));

        $list = $this->actingAsApi($cashier)->getJson('/api/v1/products?type=service');
        $list->assertOk();
        $this->assertTrue(collect($list->json('data'))->every(fn ($p) => $p['type'] === 'service'));
        $this->assertTrue(collect($list->json('data'))->contains('sku', 'SVC-DELIVERY'));
    }
}
