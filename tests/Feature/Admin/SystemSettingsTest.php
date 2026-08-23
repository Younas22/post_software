<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_update_system_settings(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_name' => 'My POS Platform',
            'support_email' => 'help@example.com',
            'maintenance_mode' => '1',
            'default_theme' => 'dark',
        ]);

        $response->assertRedirect(route('admin.settings.index'));

        $settings = app(SettingsService::class);
        $this->assertSame('My POS Platform', $settings->resolve('app_name'));
        $this->assertSame('help@example.com', $settings->resolve('support_email'));
        $this->assertSame('1', $settings->resolve('maintenance_mode'));
        $this->assertSame('dark', $settings->resolve('default_theme'));
    }

    public function test_client_side_role_cannot_view_system_settings(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
    }
}
