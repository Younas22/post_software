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

class CashSessionTest extends TestCase
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

    /**
     * Uses Sanctum::actingAs() (which sets the resolved user directly on
     * the guard) rather than a Bearer token header — a real token header
     * resolves correctly for a single request, but the guard caches its
     * resolved user for the life of the test's Application instance, so a
     * second postJson() call in the same test method with a *different*
     * user's token would otherwise still authenticate as the first user.
     */
    private function actingAsApi(User $user): self
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * @return array{0: Business, 1: Branch, 2: User}
     */
    private function cashierSetup(): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        return [$business, $branch, $cashier];
    }

    public function test_a_cashier_can_open_a_cash_session(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', [
            'branch_id' => $branch->id,
            'opening_cash' => 100,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('cash_sessions', ['branch_id' => $branch->id, 'user_id' => $cashier->id, 'status' => 'open']);
    }

    public function test_a_duplicate_open_session_for_the_same_cashier_and_branch_is_rejected(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();
        $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100])->assertCreated();

        $response = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 50]);

        $response->assertStatus(409);
        $this->assertSame(1, \App\Models\CashSession::where('branch_id', $branch->id)->where('user_id', $cashier->id)->count());
    }

    public function test_cash_in_and_cash_out_are_recorded(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');

        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/cash-in", ['amount' => 20, 'reason' => 'Float top-up'])->assertOk();
        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/cash-out", ['amount' => 5, 'reason' => 'Petty expense'])->assertOk();

        $this->assertDatabaseHas('cash_movements', ['cash_session_id' => $sessionId, 'type' => 'cash_in', 'amount' => 20]);
        $this->assertDatabaseHas('cash_movements', ['cash_session_id' => $sessionId, 'type' => 'cash_out', 'amount' => 5]);
    }

    public function test_sale_integration_is_reflected_in_expected_cash_on_close(): void
    {
        [$business, $branch, $cashier] = $this->cashierSetup();
        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'status' => 'active']);
        $product->save();
        app(StockService::class)->adjust($branch, $product, 10, 'adjustment');

        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');

        $method = PaymentMethod::where('slug', 'cash')->firstOrFail();
        app(SaleService::class)->create(
            $branch, $cashier, null,
            [['product_id' => $product->id, 'quantity' => 2]],
            [['payment_method_id' => $method->id, 'amount' => 40]],
            (string) Str::uuid(),
            ['x' => 1],
        );

        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/cash-in", ['amount' => 10])->assertOk();

        $close = $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/close", ['actual_cash' => 150]);

        $close->assertOk();
        // expected = opening(100) + cash sales(40) + cash_in(10) - cash_out(0) = 150
        $this->assertEquals(150, $close->json('data.expected_cash'));
        $this->assertEquals(0, $close->json('data.difference'));
        $this->assertSame('closed', $close->json('data.status'));
    }

    public function test_closing_with_a_shortfall_reports_a_negative_difference(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');

        $close = $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/close", ['actual_cash' => 90]);

        $close->assertOk();
        $this->assertEquals(-10, $close->json('data.difference'));
    }

    public function test_cannot_operate_on_a_closed_session(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');
        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/close", ['actual_cash' => 100])->assertOk();

        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$sessionId}/cash-in", ['amount' => 5])->assertStatus(409);
    }

    public function test_after_closing_the_cashier_can_open_a_new_session(): void
    {
        [, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $this->actingAsApi($cashier)->postJson("/api/v1/cash-sessions/{$open->json('data.id')}/close", ['actual_cash' => 100])->assertOk();

        $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 50])->assertCreated();
    }

    public function test_wrong_branch_is_rejected_when_opening(): void
    {
        [, , $cashier] = $this->cashierSetup();
        $otherBranch = Branch::factory()->create();

        $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $otherBranch->id, 'opening_cash' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_another_cashier_cannot_operate_on_someone_elses_session(): void
    {
        [$business, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');

        $otherCashier = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $otherCashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($otherCashier)->postJson("/api/v1/cash-sessions/{$sessionId}/cash-in", ['amount' => 5])->assertForbidden();
    }

    public function test_a_manager_can_close_another_cashiers_session(): void
    {
        [$business, $branch, $cashier] = $this->cashierSetup();
        $open = $this->actingAsApi($cashier)->postJson('/api/v1/cash-sessions/open', ['branch_id' => $branch->id, 'opening_cash' => 100]);
        $sessionId = $open->json('data.id');

        $manager = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $manager->roles()->attach(Role::query()->where('slug', 'manager')->firstOrFail());

        $this->actingAsApi($manager)->postJson("/api/v1/cash-sessions/{$sessionId}/close", ['actual_cash' => 100])->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/cash-sessions/open', [])->assertUnauthorized();
        $this->getJson('/api/v1/cash-sessions/current?branch_id=1')->assertUnauthorized();
    }
}
