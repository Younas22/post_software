<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseManagementTest extends TestCase
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
            'name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 10, 'status' => 'active',
        ], $overrides));
        $product->save();

        return $product;
    }

    private function supplier(Business $business): Supplier
    {
        $supplier = new Supplier();
        $supplier->business_id = $business->id;
        $supplier->fill(['name' => 'Acme Supplier', 'status' => 'active']);
        $supplier->save();

        return $supplier;
    }

    public function test_creating_a_received_purchase_computes_totals_and_increases_stock(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'purchase_price' => 5, 'discount' => 2, 'tax' => 3],
            ],
        ]);

        $response->assertCreated();
        // subtotal = 10*5=50, discount=2, tax=3 -> total = 51
        $this->assertEquals(50, $response->json('data.subtotal'));
        $this->assertEquals(51, $response->json('data.total'));
        $this->assertSame('received', $response->json('data.status'));
        $this->assertSame(10, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_frontend_supplied_totals_are_ignored_and_recomputed_server_side(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'subtotal' => 999999, // attempted spoof; not a recognized input at all
            'total' => 1, // attempted spoof
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'purchase_price' => 100],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(200, $response->json('data.total'));
    }

    public function test_pending_purchase_does_not_affect_stock_until_received(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'pending',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'purchase_price' => 5]],
        ]);

        $response->assertCreated();
        $this->assertSame(0, app(StockService::class)->currentQuantity($branch, $product));

        $purchaseId = $response->json('data.id');
        $this->actingAsApi($owner)->patchJson("/api/v1/purchases/{$purchaseId}/receive")->assertOk();

        $this->assertSame(5, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_cancelling_a_received_purchase_reverses_its_stock(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);

        $create = $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 8, 'purchase_price' => 5]],
        ]);
        $purchaseId = $create->json('data.id');
        $this->assertSame(8, app(StockService::class)->currentQuantity($branch, $product));

        $this->actingAsApi($owner)->patchJson("/api/v1/purchases/{$purchaseId}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_cannot_receive_an_already_received_purchase_again(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);

        $create = $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'purchase_price' => 5]],
        ]);
        $purchaseId = $create->json('data.id');

        $this->actingAsApi($owner)->patchJson("/api/v1/purchases/{$purchaseId}/receive")->assertStatus(409);
    }

    public function test_wrong_tenant_branch_or_supplier_is_rejected(): void
    {
        [, $business, , $owner] = $this->ownerSetup();
        $product = $this->product($business);
        $otherBusiness = Business::factory()->create();
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id]);
        $otherSupplier = $this->supplier($otherBusiness);

        $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $otherBranch->id,
            'supplier_id' => $otherSupplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_invalid_product_is_rejected(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $foreignProduct = $this->product(Business::factory()->create());

        $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1, 'purchase_price' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_rollback_on_failure_leaves_no_purchase_or_stock_change(): void
    {
        [, $business, $branch, $owner] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);
        $foreignProduct = $this->product(Business::factory()->create());

        // Second item is invalid -> the whole request must be rejected
        // before any DB row is touched (validation happens before the
        // transaction even opens here, but this also guards the service
        // layer itself never partially commits).
        $this->actingAsApi($owner)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'purchase_price' => 5],
                ['product_id' => $foreignProduct->id, 'quantity' => 5, 'purchase_price' => 5],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame(0, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_cashier_cannot_create_purchases(): void
    {
        [$client, $business, $branch, ] = $this->ownerSetup();
        $supplier = $this->supplier($business);
        $product = $this->product($business);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->postJson('/api/v1/purchases', [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'purchase_price' => 5]],
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/purchases')->assertUnauthorized();
        $this->postJson('/api/v1/purchases', [])->assertUnauthorized();
    }
}
