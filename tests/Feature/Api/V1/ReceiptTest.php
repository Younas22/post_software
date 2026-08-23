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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptTest extends TestCase
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
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * @return array{0: Branch, 1: User, 2: Sale}
     */
    private function completedSaleSetup(): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id, 'name' => 'Test Mart']);
        $branch = Branch::factory()->create(['business_id' => $business->id, 'name' => 'Main Branch']);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Cash E. Register']);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'status' => 'active']);
        $product->save();
        app(StockService::class)->adjust($branch, $product, 10, 'adjustment');

        $method = PaymentMethod::where('slug', 'cash')->firstOrFail();
        $receipt = app(SaleService::class)->create(
            $branch, $cashier, null,
            [['product_id' => $product->id, 'quantity' => 2]],
            [['payment_method_id' => $method->id, 'amount' => 40]],
            (string) Str::uuid(),
            ['seed' => 1],
        );
        $sale = Sale::where('invoice_number', $receipt['invoice_number'])->firstOrFail();

        return [$branch, $cashier, $sale];
    }

    public function test_receipt_contains_the_expected_structured_fields(): void
    {
        [, $cashier, $sale] = $this->completedSaleSetup();

        $response = $this->actingAsApi($cashier)->getJson("/api/v1/sales/{$sale->id}/receipt");

        $response->assertOk();
        $response->assertJsonPath('data.business.name', 'Test Mart');
        $response->assertJsonPath('data.branch.name', 'Main Branch');
        $response->assertJsonPath('data.cashier.name', 'Cash E. Register');
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.product_name', 'Widget');
        $response->assertJsonPath('data.payments.0.method', 'Cash');
        $this->assertEquals(40, $response->json('data.total'));
        $this->assertNull($response->json('data.customer'));
    }

    public function test_cannot_access_a_receipt_for_another_tenants_sale(): void
    {
        [, , $sale] = $this->completedSaleSetup();
        $stranger = User::factory()->create();
        $stranger->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        $this->actingAsApi($stranger)->getJson("/api/v1/sales/{$sale->id}/receipt")->assertStatus(403);
    }

    public function test_a_nonexistent_sale_id_is_not_found(): void
    {
        [, $cashier] = $this->completedSaleSetup();

        $this->actingAsApi($cashier)->getJson('/api/v1/sales/999999/receipt')->assertStatus(404);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        [, , $sale] = $this->completedSaleSetup();

        $this->getJson("/api/v1/sales/{$sale->id}/receipt")->assertUnauthorized();
    }
}
