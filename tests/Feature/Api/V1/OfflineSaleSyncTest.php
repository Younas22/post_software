<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
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

class OfflineSaleSyncTest extends TestCase
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
     * @return array{0: Business, 1: Branch, 2: Device, 3: string, 4: User, 5: Product}
     */
    private function readySetup(int $stock = 10): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);

        $device = new Device();
        $device->client_id = $client->id;
        $device->business_id = $business->id;
        $device->branch_id = $branch->id;
        $device->fill(['name' => 'POS-1', 'platform' => 'windows', 'device_identifier' => (string) Str::uuid(), 'status' => 'active', 'activated_at' => now()]);
        $device->save();
        $token = $device->createToken('POS-1')->plainTextToken;

        $cashier = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 20, 'status' => 'active']);
        $product->save();
        app(StockService::class)->adjust($branch, $product, $stock, 'adjustment');

        return [$business, $branch, $device, $token, $cashier, $product];
    }

    private function asDevice(string $token): self
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('slug', 'cash')->firstOrFail();
    }

    private function body(User $cashier, Product $product, array $overrides = []): array
    {
        return array_merge([
            'client_transaction_id' => (string) Str::uuid(),
            'cashier_id' => $cashier->id,
            'offline_created_at' => now()->subHours(2)->toIso8601String(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['payment_method_id' => $this->cashMethod()->id, 'amount' => 44]],
        ], $overrides);
    }

    // --- Offline sale ---

    public function test_an_offline_sale_is_accepted_and_preserves_its_original_timestamp(): void
    {
        [, $branch, , $token, $cashier, $product] = $this->readySetup();
        $offlineTime = now()->subHours(5);

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product, [
            'offline_created_at' => $offlineTime->toIso8601String(),
        ]));

        $response->assertCreated();
        $this->assertSame('accepted', $response->json('data.status'));
        $this->assertNotEmpty($response->json('data.server_id'));

        $sale = Sale::findOrFail($response->json('data.server_id'));
        $this->assertSame($offlineTime->toDateTimeString(), $sale->sale_date->toDateTimeString());
        $this->assertSame(8, app(StockService::class)->currentQuantity($branch, $product));
    }

    // --- Duplicate upload ---

    public function test_a_duplicate_upload_returns_the_existing_transaction_without_creating_a_second_sale(): void
    {
        [, , , $token, $cashier, $product] = $this->readySetup();
        $payload = $this->body($cashier, $product);

        $first = $this->asDevice($token)->postJson('/api/v1/sync/sales', $payload);
        $second = $this->asDevice($token)->postJson('/api/v1/sync/sales', $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.server_id'), $second->json('data.server_id'));
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_the_same_id_with_a_different_payload_is_reported_as_a_conflict(): void
    {
        [, , , $token, $cashier, $product] = $this->readySetup();
        $txnId = (string) Str::uuid();

        $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product, ['client_transaction_id' => $txnId]))
            ->assertCreated();

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product, [
            'client_transaction_id' => $txnId,
            'items' => [['product_id' => $product->id, 'quantity' => 9]],
        ]));

        $response->assertStatus(409);
        $this->assertSame('conflict', $response->json('error_type'));
    }

    // --- Timeout retry ---

    public function test_the_same_id_retried_after_a_timeout_actually_completes(): void
    {
        [$business, , , $token, $cashier, $product] = $this->readySetup();
        $txnId = (string) Str::uuid();
        $body = $this->body($cashier, $product, ['client_transaction_id' => $txnId]);

        // Simulates a first attempt that reached the server and recorded
        // its idempotency row, but crashed before the response was saved
        // (e.g. Electron lost connectivity mid-upload).
        IdempotencyKey::query()->create([
            'business_id' => $business->id,
            'client_transaction_id' => $txnId,
            'request_hash' => hash('sha256', json_encode(array_intersect_key($body, array_flip(['cashier_id', 'customer_id', 'items', 'payments'])))),
        ]);

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $body);

        $response->assertCreated();
        $this->assertDatabaseCount('sales', 1);
    }

    // --- Insufficient stock ---

    public function test_insufficient_stock_is_reported_as_retryable(): void
    {
        [, , , $token, $cashier, $product] = $this->readySetup(stock: 1);

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product, [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]));

        $response->assertStatus(409);
        $this->assertSame('retryable', $response->json('error_type'));
        $this->assertDatabaseCount('sales', 0);
    }

    // --- Blocked device ---

    public function test_a_blocked_device_cannot_upload_offline_sales(): void
    {
        [, , $device, $token, $cashier, $product] = $this->readySetup();
        $device->update(['status' => 'blocked']);

        $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product))->assertStatus(403);
    }

    // --- Expired subscription ---

    public function test_an_expired_subscription_rejects_the_upload_as_non_retryable(): void
    {
        [$business, , , $token, $cashier, $product] = $this->readySetup();
        $business->client->currentSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $product));

        $response->assertStatus(403);
        $this->assertSame('non_retryable', $response->json('error_type'));
    }

    // --- Wrong branch ---

    public function test_a_cashier_from_a_different_branch_is_rejected(): void
    {
        [$business, , , $token, , $product] = $this->readySetup();
        $otherBranch = Branch::factory()->create(['business_id' => $business->id]);
        $otherBranchCashier = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id, 'branch_id' => $otherBranch->id]);

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($otherBranchCashier, $product));

        $response->assertStatus(422)->assertJsonValidationErrors('cashier_id');
        $this->assertSame('non_retryable', $response->json('error_type'));
    }

    // --- Wrong tenant ---

    public function test_a_product_from_another_tenant_is_rejected(): void
    {
        [, , , $token, $cashier, ] = $this->readySetup();
        $foreignProduct = (function () {
            $p = new Product();
            $p->business_id = Business::factory()->create()->id;
            $p->fill(['name' => 'Foreign', 'sku' => 'FOR-'.uniqid(), 'purchase_price' => 1, 'sale_price' => 1, 'status' => 'active']);
            $p->save();

            return $p;
        })();

        $response = $this->asDevice($token)->postJson('/api/v1/sync/sales', $this->body($cashier, $foreignProduct));

        $response->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
        $this->assertSame('non_retryable', $response->json('error_type'));
    }

    // --- Concurrent upload (same key, simulated via sequential calls proving exactly-once) ---

    public function test_concurrent_style_duplicate_uploads_never_create_more_than_one_sale(): void
    {
        [, , , $token, $cashier, $product] = $this->readySetup();
        $payload = $this->body($cashier, $product);

        for ($i = 0; $i < 3; $i++) {
            $this->asDevice($token)->postJson('/api/v1/sync/sales', $payload)->assertCreated();
        }

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_unauthenticated_upload_is_rejected(): void
    {
        [, , , , $cashier, $product] = $this->readySetup();

        $this->postJson('/api/v1/sync/sales', $this->body($cashier, $product))->assertUnauthorized();
    }
}
