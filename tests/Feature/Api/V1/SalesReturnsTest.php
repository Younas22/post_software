<?php

namespace Tests\Feature\Api\V1;

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
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReturnsTest extends TestCase
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
     * @return array{0: Branch, 1: User, 2: Product, 3: Sale}
     */
    private function completedSaleSetup(int $quantitySold = 5, int $stockAfterSale = 5): array
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
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'status' => 'active']);
        $product->save();

        app(StockService::class)->adjust($branch, $product, $quantitySold + $stockAfterSale, 'adjustment');

        $method = PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
        $receipt = app(SaleService::class)->create(
            $branch, $cashier, null,
            [['product_id' => $product->id, 'quantity' => $quantitySold]],
            [['payment_method_id' => $method->id, 'amount' => $quantitySold * 20]],
            (string) Str::uuid(),
            ['seed' => 1],
        );
        $sale = Sale::where('invoice_number', $receipt['invoice_number'])->firstOrFail();

        return [$branch, $cashier, $product, $sale];
    }

    // --- Partial / full return ---

    public function test_a_partial_return_restores_partial_inventory_and_computes_refund(): void
    {
        [$branch, $cashier, $product, $sale] = $this->completedSaleSetup(quantitySold: 5, stockAfterSale: 0);
        $saleItem = $sale->items()->first();

        $response = $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'reason' => 'Customer changed mind',
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
        ]);

        $response->assertCreated();
        $this->assertEquals(40, $response->json('data.refund_amount'));
        $this->assertSame(2, app(StockService::class)->currentQuantity($branch, $product));
    }

    public function test_a_full_return_restores_all_inventory(): void
    {
        [$branch, $cashier, $product, $sale] = $this->completedSaleSetup(quantitySold: 3, stockAfterSale: 0);
        $saleItem = $sale->items()->first();

        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertSame(3, app(StockService::class)->currentQuantity($branch, $product));
    }

    // --- Excessive return ---

    public function test_an_excessive_return_is_rejected(): void
    {
        [, $cashier, , $sale] = $this->completedSaleSetup(quantitySold: 2, stockAfterSale: 0);
        $saleItem = $sale->items()->first();

        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    }

    // --- Duplicate return (returning already-returned units) ---

    public function test_a_second_return_cannot_exceed_what_remains_after_the_first(): void
    {
        [, $cashier, , $sale] = $this->completedSaleSetup(quantitySold: 3, stockAfterSale: 0);
        $saleItem = $sale->items()->first();

        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
        ])->assertCreated();

        // Only 1 remains; requesting 2 more must fail.
        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');

        // Exactly what remains still succeeds.
        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    // --- Wrong branch / wrong tenant ---

    public function test_cannot_process_a_return_for_a_sale_in_another_branch(): void
    {
        [$branch, $cashier, , $sale] = $this->completedSaleSetup();
        $saleItem = $sale->items()->first();
        $otherBranchCashier = User::factory()->create([
            'client_id' => $cashier->client_id,
            'business_id' => $cashier->business_id,
            'branch_id' => Branch::factory()->create(['business_id' => $cashier->business_id])->id,
        ]);
        $otherBranchCashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($otherBranchCashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_cannot_process_a_return_for_another_tenants_sale(): void
    {
        [, , , $sale] = $this->completedSaleSetup();
        $otherClient = Client::factory()->create();
        $otherBusiness = Business::factory()->create(['client_id' => $otherClient->id]);
        $otherBranch = Branch::factory()->create(['business_id' => $otherBusiness->id]);
        $stranger = User::factory()->create(['client_id' => $otherClient->id, 'business_id' => $otherBusiness->id, 'branch_id' => $otherBranch->id]);
        $stranger->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());
        $saleItem = $sale->items()->first();

        $this->actingAsApi($stranger)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    // --- Wrong sale item ---

    public function test_a_sale_item_not_belonging_to_the_sale_is_rejected(): void
    {
        [, $cashier, , $sale] = $this->completedSaleSetup();
        [, , , $otherSale] = $this->completedSaleSetup();
        $foreignItem = $otherSale->items()->first();

        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $foreignItem->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.sale_item_id');
    }

    // --- Permissions ---

    public function test_a_user_without_returns_permission_cannot_process_a_return(): void
    {
        [, $cashier, , $sale] = $this->completedSaleSetup();
        $saleItem = $sale->items()->first();
        $cashier->roles()->detach();

        $this->actingAsApi($cashier)->postJson("/api/v1/sales/{$sale->id}/returns", [
            'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        [, , , $sale] = $this->completedSaleSetup();

        $this->postJson("/api/v1/sales/{$sale->id}/returns", [])->assertUnauthorized();
        $this->getJson('/api/v1/returns')->assertUnauthorized();
    }
}
