<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_logo_favicon_and_set_office_address(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_name' => 'Acme POS',
            'office_address' => 'Office #12, Blue Area, Islamabad',
            'app_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'app_favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertRedirect(route('admin.settings.index'))->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);
        $logo = $settings->resolve('app_logo');
        $favicon = $settings->resolve('app_favicon');

        Storage::disk('public')->assertExists($logo);
        Storage::disk('public')->assertExists($favicon);
        $this->assertSame('Office #12, Blue Area, Islamabad', $settings->resolve('office_address'));

        // Shown on the dashboard, sidebar and browser tab.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Acme POS')
            ->assertSee('Office #12, Blue Area, Islamabad')
            ->assertSee(Storage::disk('public')->url($logo), false)
            ->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($favicon).'">', false);
    }

    public function test_replacing_and_removing_logo_deletes_old_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_super_admin' => true]);
        $settings = app(SettingsService::class);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_logo' => UploadedFile::fake()->image('a.png'),
        ]);
        $first = $settings->resolve('app_logo');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_logo' => UploadedFile::fake()->image('b.png'),
        ]);
        $second = $settings->resolve('app_logo');

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['remove_app_logo' => '1']);

        Storage::disk('public')->assertMissing($second);
        $this->assertNull($settings->resolve('app_logo'));
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors('app_logo');
    }
}
