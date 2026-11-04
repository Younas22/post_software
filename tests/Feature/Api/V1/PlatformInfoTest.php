<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_platform_endpoint_returns_company_and_support_contacts(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('app_name', 'Mapzoon POS');
        $settings->set('office_address', 'Office 12, Blue Area, Islamabad');
        $settings->set('support_email', 'help@mapzoon.pk');
        $settings->set('support_phone', '+92 300 1234567');
        $settings->set('support_live_chat_url', 'https://tawk.to/chat/abc');

        $this->getJson('/api/v1/public/platform')
            ->assertOk()
            ->assertJsonPath('data.name', 'Mapzoon POS')
            ->assertJsonPath('data.office_address', 'Office 12, Blue Area, Islamabad')
            ->assertJsonPath('data.support_email', 'help@mapzoon.pk')
            ->assertJsonPath('data.live_chat_url', 'https://tawk.to/chat/abc')
            ->assertJsonPath('data.support_whatsapp', null);
    }

    public function test_super_admin_saves_support_settings_and_live_chat_must_be_a_url(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('name="support_whatsapp"', false)
            ->assertSee('WhatsApp number');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['support_live_chat_url' => 'not a link'])
            ->assertSessionHasErrors('support_live_chat_url');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'support_whatsapp' => '+92 321 0000000',
            'support_hours' => 'Mon-Sat 10am-7pm',
            'support_live_chat_url' => 'https://wa.me/923210000000',
        ])->assertRedirect();

        $this->assertSame('+92 321 0000000', app(SettingsService::class)->resolve('support_whatsapp'));

        // Branding is a per-request singleton; the admin page above already
        // built it in this same test process, so start fresh like a new request.
        app()->forgetInstance(\App\Support\Branding::class);

        $this->getJson('/api/v1/public/platform')
            ->assertJsonPath('data.support_whatsapp', '+92 321 0000000')
            ->assertJsonPath('data.support_hours', 'Mon-Sat 10am-7pm');
    }
}
