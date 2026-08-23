<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
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
     * @return array{0: Business, 1: Branch, 2: User}
     */
    private function ownerSetup(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$business, $branch, $owner];
    }

    private function expense(Business $business, Branch $branch, array $overrides = []): Expense
    {
        return Expense::query()->create(array_merge([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'title' => 'Expense',
            'category' => 'general',
            'amount' => 10,
            'expense_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_client_owner_can_create_an_expense(): void
    {
        [$business, $branch, $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->postJson('/api/v1/expenses', [
            'branch_id' => $branch->id,
            'title' => 'Office rent',
            'category' => 'rent',
            'amount' => 500,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Office rent');
        $this->assertDatabaseHas('expenses', ['business_id' => $business->id, 'title' => 'Office rent']);
    }

    public function test_creating_an_expense_rejects_a_branch_from_another_tenant(): void
    {
        [, , $owner] = $this->ownerSetup();
        $foreignBranch = Branch::factory()->create();

        $this->actingAsApi($owner)->postJson('/api/v1/expenses', [
            'branch_id' => $foreignBranch->id,
            'title' => 'Sneaky', 'category' => 'other', 'amount' => 10, 'expense_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_cannot_view_or_update_another_tenants_expense(): void
    {
        [, , $owner] = $this->ownerSetup();
        $foreignBusiness = Business::factory()->create();
        $foreignBranch = Branch::factory()->create(['business_id' => $foreignBusiness->id]);
        $foreign = $this->expense($foreignBusiness, $foreignBranch, ['title' => 'Foreign expense']);

        $this->actingAsApi($owner)->getJson("/api/v1/expenses/{$foreign->id}")->assertStatus(403);
        $this->actingAsApi($owner)->patchJson("/api/v1/expenses/{$foreign->id}", [
            'title' => 'Hijack', 'category' => 'other', 'amount' => 1, 'expense_date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_expenses_list_is_tenant_scoped_and_filterable_by_category(): void
    {
        [$business, $branch, $owner] = $this->ownerSetup();
        $this->expense($business, $branch, ['title' => 'Utility bill', 'category' => 'utilities']);
        $this->expense($business, $branch, ['title' => 'Rent', 'category' => 'rent']);
        $otherBusiness = Business::factory()->create();
        $this->expense($otherBusiness, Branch::factory()->create(['business_id' => $otherBusiness->id]), ['title' => 'Other tenant expense']);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/expenses?category=rent');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Rent', $response->json('data.0.title'));
    }

    public function test_updating_an_expense(): void
    {
        [$business, $branch, $owner] = $this->ownerSetup();
        $expense = $this->expense($business, $branch, ['title' => 'Original']);

        $response = $this->actingAsApi($owner)->patchJson("/api/v1/expenses/{$expense->id}", [
            'title' => 'Updated', 'category' => 'general', 'amount' => 25, 'expense_date' => now()->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'Updated');
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'title' => 'Updated']);
    }

    public function test_deleting_an_expense_soft_deletes_it(): void
    {
        [$business, $branch, $owner] = $this->ownerSetup();
        $expense = $this->expense($business, $branch);

        $this->actingAsApi($owner)->deleteJson("/api/v1/expenses/{$expense->id}")->assertOk();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_validation_requires_core_fields(): void
    {
        [, , $owner] = $this->ownerSetup();

        $this->actingAsApi($owner)->postJson('/api/v1/expenses', [])
            ->assertStatus(422)->assertJsonValidationErrors(['branch_id', 'title', 'category', 'amount', 'expense_date']);
    }

    public function test_cashier_cannot_manage_expenses(): void
    {
        [$business, , ] = $this->ownerSetup();
        $cashier = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id]);
        $cashier->roles()->attach(Role::query()->where('slug', 'cashier')->firstOrFail());

        $this->actingAsApi($cashier)->getJson('/api/v1/expenses')->assertForbidden();
    }

    public function test_manager_can_create_and_update_but_not_delete_expenses(): void
    {
        [$business, $branch, ] = $this->ownerSetup();
        $manager = User::factory()->create(['client_id' => $business->client_id, 'business_id' => $business->id]);
        $manager->roles()->attach(Role::query()->where('slug', 'manager')->firstOrFail());

        $this->actingAsApi($manager)->postJson('/api/v1/expenses', [
            'branch_id' => $branch->id, 'title' => 'Supplies', 'category' => 'supplies', 'amount' => 15, 'expense_date' => now()->toDateString(),
        ])->assertCreated();

        $expense = Expense::where('title', 'Supplies')->firstOrFail();
        $this->actingAsApi($manager)->deleteJson("/api/v1/expenses/{$expense->id}")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/expenses')->assertUnauthorized();
    }
}
