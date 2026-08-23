<?php

namespace Tests\Feature\Admin;

use App\Models\Feature;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageFeatureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_super_admin_can_create_a_package_with_assigned_features(): void
    {
        $pos = Feature::factory()->create(['name' => 'POS']);
        $customers = Feature::factory()->create(['name' => 'Customers']);
        $multiBranch = Feature::factory()->create(['name' => 'Multi Branch']);

        $response = $this->actingAs($this->admin())->post(route('admin.packages.store'), [
            'name' => 'Starter Plus',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'features' => [$pos->id, $customers->id],
        ]);

        $package = Package::query()->where('name', 'Starter Plus')->firstOrFail();
        $response->assertRedirect(route('admin.packages.show', $package));

        $assignedIds = $package->features()->pluck('features.id')->all();
        $this->assertContains($pos->id, $assignedIds);
        $this->assertContains($customers->id, $assignedIds);
        $this->assertNotContains($multiBranch->id, $assignedIds);
    }

    public function test_updating_a_package_resyncs_its_features(): void
    {
        $package = Package::factory()->create();
        $keep = Feature::factory()->create();
        $drop = Feature::factory()->create();
        $add = Feature::factory()->create();
        $package->features()->sync([$keep->id, $drop->id]);

        $this->actingAs($this->admin())->put(route('admin.packages.update', $package), [
            'name' => $package->name,
            'price' => $package->price,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'features' => [$keep->id, $add->id],
        ]);

        $assignedIds = $package->features()->pluck('features.id')->all();
        $this->assertContains($keep->id, $assignedIds);
        $this->assertContains($add->id, $assignedIds);
        $this->assertNotContains($drop->id, $assignedIds);
    }

    public function test_super_admin_can_activate_and_deactivate_a_package(): void
    {
        $package = Package::factory()->create(['status' => 'active']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.packages.deactivate', $package))->assertRedirect();
        $this->assertSame('inactive', $package->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.packages.activate', $package))->assertRedirect();
        $this->assertSame('active', $package->fresh()->status);
    }

    public function test_super_admin_can_create_and_update_a_feature(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.features.store'), [
            'name' => 'Appointments',
            'status' => 'active',
        ]);

        $feature = Feature::query()->where('name', 'Appointments')->firstOrFail();
        $response->assertRedirect(route('admin.features.index'));
        $this->assertSame('appointments', $feature->slug);

        $this->actingAs($admin)->put(route('admin.features.update', $feature), [
            'name' => 'Appointments',
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertSame('inactive', $feature->fresh()->status);
    }

    public function test_client_side_role_cannot_manage_packages(): void
    {
        $role = Role::query()->where('slug', 'client-owner')->firstOrFail();
        $user = User::factory()->create(['is_super_admin' => false]);
        $user->roles()->attach($role);

        $this->assertFalse($user->hasPermission('packages.view'));
        $this->actingAs($user)->get(route('admin.packages.index'))->assertForbidden();
    }
}
