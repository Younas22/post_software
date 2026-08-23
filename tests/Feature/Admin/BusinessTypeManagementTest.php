<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_super_admin_can_create_a_business_type(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.business-types.store'), [
            'name' => 'Bakery',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.business-types.index'));
        $this->assertDatabaseHas('business_types', ['name' => 'Bakery', 'slug' => 'bakery', 'is_active' => true]);
    }

    public function test_super_admin_can_edit_and_toggle_a_business_type(): void
    {
        $type = BusinessType::factory()->create(['name' => 'Old', 'is_active' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.business-types.update', $type), [
            'name' => 'New',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('New', $type->fresh()->name);

        $this->actingAs($admin)->patch(route('admin.business-types.deactivate', $type))->assertRedirect();
        $this->assertFalse((bool) $type->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.business-types.activate', $type))->assertRedirect();
        $this->assertTrue((bool) $type->fresh()->is_active);
    }

    public function test_slug_must_be_unique(): void
    {
        BusinessType::factory()->create(['slug' => 'grocery']);

        $response = $this->actingAs($this->admin())->post(route('admin.business-types.store'), [
            'name' => 'Grocery',
            'slug' => 'grocery',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_business_settings_panel_saves_business_scoped_values(): void
    {
        $business = \App\Models\Business::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.businesses.settings.update', $business), [
            'tax_rate' => '8.5',
            'invoice_prefix' => 'BIZ-',
        ]);

        $response->assertRedirect(route('admin.businesses.show', $business));
        $this->assertSame('8.5', app(\App\Services\SettingsService::class)->resolve('tax_rate', business: $business));
        $this->assertSame('BIZ-', app(\App\Services\SettingsService::class)->resolve('invoice_prefix', business: $business));
    }
}
