<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentsTest extends TestCase
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
     * @return array{0: Business, 1: Branch, 2: User, 3: Customer, 4: Product}
     */
    private function setupStore(bool $installmentsEnabled = true): array
    {
        $type = BusinessType::factory()->create([
            'slug' => 'installment-test',
            'modules' => ['hidden' => [], 'enabled' => $installmentsEnabled ? ['installments'] : []],
            'terminology' => ['customer' => 'Buyer'],
            'theme' => ['primary_color' => '#7c3aed'],
        ]);
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id, 'business_type_id' => $type->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        $customer = new Customer(['name' => 'Ali Khan', 'phone' => '03001234567', 'status' => 'active']);
        $customer->business_id = $business->id;
        $customer->save();

        $product = new Product(['name' => 'LED TV', 'sku' => 'TV-1', 'type' => 'product', 'purchase_price' => 50000, 'sale_price' => 60000, 'unit' => 'pcs', 'minimum_stock' => 0, 'status' => 'active']);
        $product->business_id = $business->id;
        $product->save();

        Inventory::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity' => 5]);

        return [$business, $branch, $owner, $customer, $product];
    }

    private function planPayload(Branch $branch, Customer $customer, Product $product, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'markup_percent' => 10,
            'down_payment' => 6000,
            'installments_count' => 6,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'first_due_date' => now()->addMonth()->toDateString(),
            'guarantor_name' => 'Bilal',
        ], $overrides);
    }

    public function test_owner_creates_a_plan_with_schedule_and_stock_leaves(): void
    {
        [, $branch, $owner, $customer, $product] = $this->setupStore();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/installments', $this->planPayload($branch, $customer, $product));

        // 60,000 + 10% = 66,000 total; 6,000 down; 60,000 over 6 months = 10,000 each.
        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '66000.00')
            ->assertJsonPath('data.financed_amount', '60000.00')
            ->assertJsonPath('data.installment_amount', '10000.00')
            ->assertJsonPath('data.balance', '60000.00')
            ->assertJsonCount(6, 'data.schedules')
            ->assertJsonCount(1, 'data.payments');

        $this->assertSame(4, Inventory::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_collecting_payments_allocates_oldest_first_and_completes_the_plan(): void
    {
        [, $branch, $owner, $customer, $product] = $this->setupStore();
        $planId = $this->actingAsApi($owner)->postJson('/api/v1/installments', $this->planPayload($branch, $customer, $product))->json('data.id');

        $this->actingAsApi($owner)->postJson("/api/v1/installments/{$planId}/payments", [
            'amount' => 15000,
            'payment_date' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.plan.schedules.0.status', 'paid')
            ->assertJsonPath('data.plan.schedules.1.paid_amount', '5000.00')
            ->assertJsonPath('data.plan.balance', '45000.00');

        $this->actingAsApi($owner)->postJson("/api/v1/installments/{$planId}/payments", [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->actingAsApi($owner)->postJson("/api/v1/installments/{$planId}/payments", [
            'amount' => 45000,
            'payment_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.plan.status', 'completed');
    }

    public function test_cancelling_returns_stock(): void
    {
        [, $branch, $owner, $customer, $product] = $this->setupStore();
        $planId = $this->actingAsApi($owner)->postJson('/api/v1/installments', $this->planPayload($branch, $customer, $product))->json('data.id');

        $this->actingAsApi($owner)->patchJson("/api/v1/installments/{$planId}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, Inventory::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_summary_and_list(): void
    {
        [, $branch, $owner, $customer, $product] = $this->setupStore();
        $this->actingAsApi($owner)->postJson('/api/v1/installments', $this->planPayload($branch, $customer, $product, [
            'start_date' => now()->subMonths(2)->toDateString(),
            'first_due_date' => now()->subMonth()->toDateString(),
        ]))->assertCreated();

        $this->actingAsApi($owner)->getJson('/api/v1/installments/summary')
            ->assertOk()
            ->assertJsonPath('data.active_count', 1)
            ->assertJsonPath('data.overdue_plans', 1)
            ->assertJsonPath('data.outstanding', '60000.00');

        $this->actingAsApi($owner)->getJson('/api/v1/installments?status=overdue')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_business_type_without_the_module_cannot_create_plans(): void
    {
        [, $branch, $owner, $customer, $product] = $this->setupStore(installmentsEnabled: false);

        $this->actingAsApi($owner)->postJson('/api/v1/installments', $this->planPayload($branch, $customer, $product))
            ->assertForbidden();
    }

    public function test_config_and_public_branding_expose_the_business_type(): void
    {
        [, , $owner] = $this->setupStore();

        $this->actingAsApi($owner)->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.business_type.slug', 'installment-test')
            ->assertJsonPath('data.business_type.terminology.customer', 'Buyer')
            ->assertJsonPath('data.business_type.terminology.product', 'Product')
            ->assertJsonPath('data.business_type.theme.primary_color', '#7c3aed')
            ->assertJsonPath('data.business_type.modules.enabled', ['installments']);

        $this->getJson('/api/v1/public/branding/installment-test')
            ->assertOk()->assertJsonPath('data.terminology.customer', 'Buyer');

        $this->getJson('/api/v1/public/branding/does-not-exist')->assertNotFound();
    }
}
