<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Client;
use App\Models\Device;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Business, 1: Branch, 2: Device, 3: string}
     */
    private function deviceSetup(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);

        $device = new Device();
        $device->client_id = $client->id;
        $device->business_id = $business->id;
        $device->branch_id = $branch->id;
        $device->fill(['name' => 'Test Device', 'platform' => 'windows', 'device_identifier' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'active', 'activated_at' => now()]);
        $device->save();

        $token = $device->createToken('Test Device')->plainTextToken;

        return [$business, $branch, $device, $token];
    }

    private function asDevice(string $token): self
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function product(Business $business, array $overrides = []): Product
    {
        $product = new Product();
        $product->business_id = $business->id;
        $product->fill(array_merge(['name' => 'Widget', 'sku' => 'SKU-'.uniqid(), 'purchase_price' => 5, 'sale_price' => 10, 'status' => 'active'], $overrides));
        $product->save();

        return $product;
    }

    // --- Bootstrap ---

    public function test_bootstrap_returns_business_branch_and_configuration(): void
    {
        [$business, $branch, , $token] = $this->deviceSetup();

        $response = $this->asDevice($token)->getJson('/api/v1/sync/bootstrap');

        $response->assertOk();
        $this->assertSame($business->id, $response->json('data.business.id'));
        $this->assertSame($branch->id, $response->json('data.branch.id'));
        $this->assertIsArray($response->json('data.payment_methods'));
        $this->assertIsArray($response->json('data.roles'));
        $this->assertNotEmpty($response->json('data.server_time'));
    }

    // --- Initial sync ---

    public function test_initial_sync_returns_all_current_products_and_a_cursor(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $this->product($business, ['name' => 'Alpha']);
        $this->product($business, ['name' => 'Beta']);

        $response = $this->asDevice($token)->getJson('/api/v1/sync/products');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertNotEmpty($response->json('data.cursor'));
        $this->assertEmpty($response->json('data.deleted_ids'));
    }

    // --- Incremental sync: new / updated / deleted product ---

    public function test_incremental_sync_returns_only_a_newly_created_product(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $this->product($business, ['name' => 'Existing']);

        $initial = $this->asDevice($token)->getJson('/api/v1/sync/products');
        $cursor = $initial->json('data.cursor');

        Carbon::setTestNow(now()->addSecond());
        $this->product($business, ['name' => 'Freshly Added']);

        $incremental = $this->asDevice($token)->getJson('/api/v1/sync/products?since='.urlencode($cursor));

        $incremental->assertOk();
        $this->assertCount(1, $incremental->json('data.data'));
        $this->assertSame('Freshly Added', $incremental->json('data.data.0.name'));

        Carbon::setTestNow();
    }

    public function test_incremental_sync_returns_an_updated_product(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $product = $this->product($business, ['name' => 'Original', 'sale_price' => 10]);

        $initial = $this->asDevice($token)->getJson('/api/v1/sync/products');
        $cursor = $initial->json('data.cursor');

        Carbon::setTestNow(now()->addSecond());
        $product->sale_price = 15;
        $product->save();

        $incremental = $this->asDevice($token)->getJson('/api/v1/sync/products?since='.urlencode($cursor));

        $incremental->assertOk();
        $this->assertCount(1, $incremental->json('data.data'));
        $this->assertEquals(15, $incremental->json('data.data.0.sale_price'));

        Carbon::setTestNow();
    }

    public function test_incremental_sync_reports_a_deleted_product_by_id(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $product = $this->product($business, ['name' => 'Doomed']);

        $initial = $this->asDevice($token)->getJson('/api/v1/sync/products');
        $cursor = $initial->json('data.cursor');

        Carbon::setTestNow(now()->addSecond());
        $product->delete();

        $incremental = $this->asDevice($token)->getJson('/api/v1/sync/products?since='.urlencode($cursor));

        $incremental->assertOk();
        $this->assertEmpty($incremental->json('data.data'));
        $this->assertContains($product->id, $incremental->json('data.deleted_ids'));

        Carbon::setTestNow();
    }

    public function test_a_second_incremental_sync_with_no_changes_returns_nothing(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $this->product($business);

        $first = $this->asDevice($token)->getJson('/api/v1/sync/products');
        $second = $this->asDevice($token)->getJson('/api/v1/sync/products?since='.urlencode($first->json('data.cursor')));

        $second->assertOk();
        $this->assertEmpty($second->json('data.data'));
        $this->assertEmpty($second->json('data.deleted_ids'));
    }

    // --- Inventory changes ---

    public function test_inventory_sync_reflects_stock_changes_incrementally(): void
    {
        [$business, $branch, , $token] = $this->deviceSetup();
        $product = $this->product($business);
        app(StockService::class)->adjust($branch, $product, 10, 'adjustment');

        $initial = $this->asDevice($token)->getJson('/api/v1/sync/inventory');
        $this->assertCount(1, $initial->json('data.data'));
        $this->assertSame(10, $initial->json('data.data.0.quantity'));
        $cursor = $initial->json('data.cursor');

        Carbon::setTestNow(now()->addSecond());
        app(StockService::class)->adjust($branch, $product, -3, 'sale');

        $incremental = $this->asDevice($token)->getJson('/api/v1/sync/inventory?since='.urlencode($cursor));
        $incremental->assertOk();
        $this->assertCount(1, $incremental->json('data.data'));
        $this->assertSame(7, $incremental->json('data.data.0.quantity'));

        Carbon::setTestNow();
    }

    public function test_inventory_sync_is_scoped_to_the_devices_own_branch(): void
    {
        [$business, $branch, , $token] = $this->deviceSetup();
        $otherBranch = Branch::factory()->create(['business_id' => $business->id]);
        $product = $this->product($business);
        app(StockService::class)->adjust($branch, $product, 5, 'adjustment');
        app(StockService::class)->adjust($otherBranch, $product, 99, 'adjustment');

        $response = $this->asDevice($token)->getJson('/api/v1/sync/inventory');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(5, $response->json('data.data.0.quantity'));
    }

    // --- Wrong device ---

    public function test_a_blocked_device_cannot_sync(): void
    {
        [, , $device, $token] = $this->deviceSetup();
        $device->update(['status' => 'blocked']);

        $this->asDevice($token)->getJson('/api/v1/sync/products')->assertStatus(403);
    }

    public function test_an_invalid_token_cannot_sync(): void
    {
        $this->withHeader('Authorization', 'Bearer bogus-token')->getJson('/api/v1/sync/products')->assertUnauthorized();
    }

    public function test_a_user_token_cannot_be_used_to_sync(): void
    {
        [$business, , , ] = $this->deviceSetup();
        $user = \App\Models\User::factory()->create(['business_id' => $business->id]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken)
            ->getJson('/api/v1/sync/products')->assertStatus(401);
    }

    // --- Wrong business ---

    public function test_a_device_never_receives_another_businesss_products(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        $this->product($business, ['name' => 'Mine']);
        $this->product(Business::factory()->create(), ['name' => 'Not Mine']);

        $response = $this->asDevice($token)->getJson('/api/v1/sync/products');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Mine', $response->json('data.data.0.name'));
    }

    // --- Pagination ---

    public function test_products_sync_is_paginated(): void
    {
        [$business, , , $token] = $this->deviceSetup();
        for ($i = 0; $i < 5; $i++) {
            $this->product($business, ['sku' => 'PG-SKU-'.$i]);
        }

        $response = $this->asDevice($token)->getJson('/api/v1/sync/products?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(5, $response->json('data.meta.total'));
        $this->assertTrue($response->json('data.meta.has_more'));
    }

    // --- Malformed cursor ---

    public function test_a_malformed_cursor_is_rejected_cleanly(): void
    {
        [, , , $token] = $this->deviceSetup();

        $this->asDevice($token)->getJson('/api/v1/sync/products?since=not-a-real-cursor')->assertStatus(422);
    }
}
