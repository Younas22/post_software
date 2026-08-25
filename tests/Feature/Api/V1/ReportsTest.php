<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
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

class ReportsTest extends TestCase
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
     * @return array{0: Business, 1: Branch, 2: User, 3: Product}
     */
    private function reportableSetup(): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'status' => 'active']);
        $product->save();
        app(StockService::class)->adjust($branch, $product, 50, 'adjustment');

        $method = PaymentMethod::where('slug', 'cash')->firstOrFail();
        app(SaleService::class)->create(
            $branch, $owner, null,
            [['product_id' => $product->id, 'quantity' => 3]],
            [['payment_method_id' => $method->id, 'amount' => 60]],
            (string) Str::uuid(),
            ['seed' => 1],
        );

        return [$business, $branch, $owner, $product];
    }

    public function test_sales_report_returns_correct_totals(): void
    {
        [, , $owner] = $this->reportableSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/sales');

        $response->assertOk();
        $this->assertEquals(60, $response->json('data.summary.subtotal'));
        $this->assertEquals(1, $response->json('data.summary.transaction_count'));
    }

    public function test_sales_report_date_filtering_excludes_out_of_range_sales(): void
    {
        [, , $owner] = $this->reportableSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/sales?date_from='.now()->addDay()->toDateString());

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.summary.transaction_count'));
    }

    public function test_sales_report_branch_filtering(): void
    {
        [$business, $branch, $owner] = $this->reportableSetup();
        $otherBranch = Branch::factory()->create(['business_id' => $business->id]);

        $response = $this->actingAsApi($owner)->getJson("/api/v1/reports/sales?branch_id={$otherBranch->id}");

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.summary.transaction_count'));
    }

    public function test_a_fixed_business_actor_cannot_use_the_business_id_param_to_view_another_business(): void
    {
        [, , $owner] = $this->reportableSetup();
        $otherBusiness = Business::factory()->create();

        // $owner has a fixed business_id of their own, so this spoofed
        // param is simply ignored in favor of it — proving the param can
        // never override an actor's own scope, not merely that it's
        // rejected outright.
        $response = $this->actingAsApi($owner)->getJson("/api/v1/reports/sales?business_id={$otherBusiness->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.summary.transaction_count'));
    }

    public function test_a_multi_business_owner_cannot_report_on_another_clients_business_via_the_param(): void
    {
        [$business, , $owner] = $this->reportableSetup();
        // A genuinely multi-business owner: a second business under the
        // SAME client, so User::defaultBusinessId() can't resolve one
        // unambiguously and business_id legitimately must come from the
        // request — which is still verified against their own client.
        // (A single-business owner's request param is now ignored entirely
        // in favor of their one real business — see the next test.)
        Business::factory()->create(['client_id' => $business->client_id]);
        $owner->forceFill(['business_id' => null])->save();
        $otherClientsBusiness = Business::factory()->create();

        $response = $this->actingAsApi($owner)->getJson("/api/v1/reports/sales?business_id={$otherClientsBusiness->id}");

        $response->assertStatus(403);
    }

    public function test_a_single_business_owner_with_no_fixed_business_id_still_resolves_their_own_business(): void
    {
        // The common real-world shape: a Client Owner account has no fixed
        // business_id (they're free to run several), but their client only
        // ever created one business. Without User::defaultBusinessId()'s
        // fallback, this account could not use ANY business-scoped screen
        // (branches, products, dashboard, reports, ...) at all — found via
        // a real account hitting exactly this. A malicious/wrong business_id
        // param must be silently ignored, not followed.
        [$business, , $owner] = $this->reportableSetup();
        $owner->forceFill(['business_id' => null])->save();
        $otherClientsBusiness = Business::factory()->create();

        $response = $this->actingAsApi($owner)->getJson("/api/v1/reports/sales?business_id={$otherClientsBusiness->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.summary.transaction_count'));
    }

    public function test_reports_are_denied_without_the_permission(): void
    {
        [$business, $branch] = $this->reportableSetup();
        $cashier = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/reports/sales')->assertStatus(403);
    }

    public function test_reports_are_denied_when_subscription_is_inactive(): void
    {
        [$business, , $owner] = $this->reportableSetup();
        $business->client->currentSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->actingAsApi($owner)->getJson('/api/v1/reports/sales')->assertStatus(403);
    }

    public function test_reports_are_denied_when_the_reports_feature_is_disabled(): void
    {
        [$business, , $owner] = $this->reportableSetup();
        $business->featureOverrides()->attach(
            \App\Models\Feature::query()->where('slug', 'reports')->firstOrFail()->id,
            ['enabled' => false],
        );

        $this->actingAsApi($owner)->getJson('/api/v1/reports/sales')->assertStatus(403);
    }

    public function test_purchases_report_returns_totals(): void
    {
        [$business, $branch, $owner, $product] = $this->reportableSetup();
        $supplier = new \App\Models\Supplier();
        $supplier->business_id = $business->id;
        $supplier->fill(['name' => 'Supplier Co', 'status' => 'active']);
        $supplier->save();

        app(\App\Services\PurchaseService::class)->create(
            $branch, $supplier, $owner,
            [['product_id' => $product->id, 'quantity' => 5, 'purchase_price' => 5]],
            null, now()->toDateString(), null,
        );

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/purchases');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.summary.purchase_count'));
        $this->assertEquals(25, $response->json('data.summary.total'));
    }

    public function test_inventory_report_reflects_current_stock(): void
    {
        [, , $owner] = $this->reportableSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/inventory');

        $response->assertOk();
        // 50 stocked, 3 sold -> 47 remain
        $this->assertEquals(47, $response->json('data.summary.total_units'));
    }

    public function test_products_report_ranks_by_quantity_sold(): void
    {
        [, , $owner, $product] = $this->reportableSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/products');

        $response->assertOk();
        $this->assertSame($product->id, $response->json('data.products.data.0.product_id'));
        $this->assertEquals(3, $response->json('data.products.data.0.quantity_sold'));
    }

    public function test_customers_report_aggregates_spend_per_customer(): void
    {
        [$business, $branch, $owner, $product] = $this->reportableSetup();
        $customer = new \App\Models\Customer();
        $customer->business_id = $business->id;
        $customer->fill(['name' => 'Repeat Buyer', 'status' => 'active']);
        $customer->save();
        $method = PaymentMethod::where('slug', 'cash')->firstOrFail();

        app(StockService::class)->adjust($branch, $product, 10, 'adjustment');
        app(SaleService::class)->create(
            $branch, $owner, $customer,
            [['product_id' => $product->id, 'quantity' => 2]],
            [['payment_method_id' => $method->id, 'amount' => 40]],
            (string) Str::uuid(),
            ['seed' => 2],
        );

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/customers');

        $response->assertOk();
        $this->assertEquals(40, $response->json('data.customers.data.0.total_spent'));
    }

    public function test_profit_report_computes_estimated_gross_profit(): void
    {
        [, , $owner] = $this->reportableSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/profit');

        $response->assertOk();
        // revenue 60 (3 * 20), cost estimate 3 * 5 = 15 -> profit 45
        $this->assertEquals(60, $response->json('data.revenue'));
        $this->assertEquals(15, $response->json('data.estimated_cost'));
        $this->assertEquals(45, $response->json('data.estimated_gross_profit'));
    }

    public function test_expenses_report_returns_real_totals_now_that_the_expense_module_exists(): void
    {
        [$business, $branch, $owner] = $this->reportableSetup();

        \App\Models\Expense::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'user_id' => $owner->id,
            'title' => 'Electricity bill',
            'category' => 'utilities',
            'amount' => 25,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/expenses');

        $response->assertOk();
        $this->assertEquals(25, $response->json('data.summary.total'));
        $this->assertEquals(1, $response->json('data.summary.expense_count'));
        $this->assertEquals('utilities', $response->json('data.by_category.0.category'));
    }

    public function test_expenses_report_is_tenant_scoped(): void
    {
        [$business, $branch, $owner] = $this->reportableSetup();
        [$otherBusiness, $otherBranch, $otherOwner] = $this->reportableSetup();

        \App\Models\Expense::query()->create([
            'business_id' => $otherBusiness->id,
            'branch_id' => $otherBranch->id,
            'user_id' => $otherOwner->id,
            'title' => 'Other business rent',
            'category' => 'rent',
            'amount' => 999,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/reports/expenses');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.summary.total'));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/reports/sales')->assertUnauthorized();
    }
}
