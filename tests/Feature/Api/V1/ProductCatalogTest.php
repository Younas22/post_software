<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
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
     * @return array{0: Client, 1: Business, 2: User}
     */
    private function ownerSetup(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$client, $business, $owner];
    }

    private function category(Business $business, array $overrides = []): Category
    {
        $category = new Category();
        $category->business_id = $business->id;
        $category->fill(array_merge(['name' => 'Beverages', 'slug' => 'beverages', 'status' => 'active'], $overrides));
        $category->save();

        return $category;
    }

    private function brand(Business $business, array $overrides = []): Brand
    {
        $brand = new Brand();
        $brand->business_id = $business->id;
        $brand->fill(array_merge(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active'], $overrides));
        $brand->save();

        return $brand;
    }

    private function product(Business $business, array $overrides = []): Product
    {
        $product = new Product();
        $product->business_id = $business->id;
        $product->category_id = $overrides['category_id'] ?? null;
        $product->brand_id = $overrides['brand_id'] ?? null;
        unset($overrides['category_id'], $overrides['brand_id']);
        $product->fill(array_merge([
            'name' => 'Cola 500ml', 'sku' => 'SKU-1', 'barcode' => 'BAR-1',
            'purchase_price' => 10, 'sale_price' => 15, 'unit' => 'pcs', 'status' => 'active',
        ], $overrides));
        $product->save();

        return $product;
    }

    // --- Categories / Brands basic CRUD + tenant isolation ---

    public function test_client_owner_can_create_a_category(): void
    {
        [, $business, $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/categories', [
            'name' => 'Snacks',
            'status' => 'active',
        ]);

        $response->assertCreated()->assertJsonPath('data.slug', 'snacks');
        $this->assertDatabaseHas('categories', ['business_id' => $business->id, 'slug' => 'snacks']);
    }

    public function test_duplicate_category_name_within_same_business_is_rejected(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $this->category($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/categories', [
            'name' => 'Beverages',
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_client_owner_can_create_a_brand(): void
    {
        [, , $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/brands', [
            'name' => 'Acme',
            'status' => 'active',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Acme');
    }

    public function test_cannot_view_another_tenants_category(): void
    {
        [, , $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();
        $otherCategory = $this->category($otherBusiness);

        $this->actingAsApi($owner)->getJson("/api/v1/categories/{$otherCategory->id}")->assertStatus(403);
    }

    // --- Products CRUD ---

    public function test_client_owner_can_create_a_product_with_category_and_brand(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $category = $this->category($business);
        $brand = $this->brand($business);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cola 500ml',
            'sku' => 'SKU-100',
            'barcode' => 'BAR-100',
            'purchase_price' => 10,
            'sale_price' => 15,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-100')
            ->assertJsonPath('data.category.id', $category->id);
        $this->assertDatabaseHas('products', ['business_id' => $business->id, 'sku' => 'SKU-100']);
    }

    public function test_creating_a_product_ignores_a_spoofed_business_id(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'business_id' => $otherBusiness->id, // attempted escalation; actor has a fixed business_id
            'name' => 'Sneaky',
            'sku' => 'SKU-SNEAKY',
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertSame($business->id, Product::where('sku', 'SKU-SNEAKY')->first()->business_id);
    }

    public function test_rejects_a_category_belonging_to_a_different_business(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();
        $foreignCategory = $this->category($otherBusiness);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'category_id' => $foreignCategory->id,
            'name' => 'Bad Category Product',
            'sku' => 'SKU-BADCAT',
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_rejects_duplicate_sku_within_the_same_business(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $this->product($business, ['sku' => 'DUP-SKU', 'barcode' => 'DUP-A']);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Another Product',
            'sku' => 'DUP-SKU',
            'barcode' => 'DUP-B',
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('sku');
    }

    public function test_rejects_duplicate_barcode_within_the_same_business(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $this->product($business, ['sku' => 'ORIG-SKU', 'barcode' => 'DUP-BARCODE']);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Another Product',
            'sku' => 'DIFFERENT-SKU',
            'barcode' => 'DUP-BARCODE',
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('barcode');
    }

    public function test_same_sku_is_allowed_across_different_businesses(): void
    {
        [$client, $business, $owner] = $this->ownerSetup();
        $secondBusiness = Business::factory()->create(['client_id' => $client->id]);
        $this->product($secondBusiness, ['sku' => 'SHARED-SKU']);

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Own Product',
            'sku' => 'SHARED-SKU',
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);

        $response->assertCreated();
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        [, , $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/products', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'sku', 'purchase_price', 'sale_price', 'status']);
    }

    public function test_cannot_view_or_modify_another_tenants_product(): void
    {
        [, , $owner] = $this->ownerSetup();
        $otherBusiness = Business::factory()->create();
        $foreignProduct = $this->product($otherBusiness);

        $this->actingAsApi($owner)->getJson("/api/v1/products/{$foreignProduct->id}")->assertStatus(403);
        $this->actingAsApi($owner)->patchJson("/api/v1/products/{$foreignProduct->id}", ['name' => 'Hijack'])->assertStatus(403);
        $this->assertNotSame('Hijack', $foreignProduct->fresh()->name);
    }

    public function test_client_owner_can_update_a_product(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $product = $this->product($business);

        $response = $this->actingAsApi($owner)->patchJson("/api/v1/products/{$product->id}", [
            'sale_price' => 20,
            'status' => 'inactive',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertEquals(20, $product->fresh()->sale_price);
    }

    public function test_deleting_a_product_soft_deletes_it(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $product = $this->product($business);

        $this->actingAsApi($owner)->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // --- Listing: search, filters, pagination ---

    public function test_products_list_is_scoped_to_the_actors_own_business_and_supports_search(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $this->product($business, ['name' => 'Findable Widget', 'sku' => 'FIND-1', 'barcode' => 'FINDBAR-1']);
        $this->product(Business::factory()->create(), ['sku' => 'OTHER-1', 'barcode' => 'OTHERBAR-1']); // another tenant

        $response = $this->actingAsApi($owner)->getJson('/api/v1/products?search=Findable');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Findable Widget', $response->json('data')[0]['name']);
    }

    public function test_products_list_supports_category_and_status_filters_and_pagination(): void
    {
        [, $business, $owner] = $this->ownerSetup();
        $category = $this->category($business);
        for ($i = 1; $i <= 3; $i++) {
            $this->product($business, ['category_id' => $category->id, 'sku' => "CAT-SKU-{$i}", 'barcode' => "CAT-BAR-{$i}"]);
        }
        $this->product($business, ['sku' => 'UNCAT-SKU', 'barcode' => 'UNCAT-BAR', 'status' => 'inactive']);

        $response = $this->actingAsApi($owner)->getJson("/api/v1/products?category_id={$category->id}&per_page=2");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta')['total']);

        $inactiveOnly = $this->actingAsApi($owner)->getJson('/api/v1/products?status=inactive');
        $this->assertCount(1, $inactiveOnly->json('data'));
    }

    // --- Permissions ---

    public function test_cashier_can_view_but_not_create_products(): void
    {
        [, $business, ] = $this->ownerSetup();
        $cashier = User::factory()->create(['client_id' => (fn () => $business->client_id)(), 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/products')->assertOk();
        $this->actingAsApi($cashier)->postJson('/api/v1/products', [])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->postJson('/api/v1/products', [])->assertUnauthorized();
    }
}
