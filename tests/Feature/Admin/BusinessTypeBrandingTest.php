<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessTypeBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_edit_page_renders_branding_sections(): void
    {
        $type = BusinessType::factory()->create(['slug' => 'dental-clinic']);

        $this->actingAs($this->admin())->get(route('admin.business-types.edit', $type))
            ->assertOk()
            ->assertSee('Theme &amp; login page', false)
            ->assertSee('Terminology')
            ->assertSee('/login/dental-clinic');
    }

    public function test_super_admin_saves_theme_terminology_modules_and_login_image(): void
    {
        Storage::fake('public');
        $type = BusinessType::factory()->create(['name' => 'Dental', 'slug' => 'dental']);

        $this->actingAs($this->admin())->put(route('admin.business-types.update', $type), [
            'name' => 'Dental Clinic',
            'is_active' => '1',
            'icon' => 'stethoscope',
            'theme' => [
                'primary_color' => '#0284C7',
                'sidebar_style' => 'brand',
                'login_headline' => 'Healthy smiles',
                'login_tagline' => 'Clinic billing',
                'login_highlights' => "Treatments\n\nPatient history\n",
            ],
            'login_image' => UploadedFile::fake()->image('clinic.jpg', 1600, 1200),
            'terminology' => ['customer' => 'Patient', 'customers' => 'Patients', 'product' => ''],
            'modules' => ['hidden' => ['purchases'], 'enabled' => ['installments']],
        ])->assertRedirect(route('admin.business-types.index'));

        $type->refresh();
        $this->assertSame('#0284c7', $type->theme['primary_color']);
        $this->assertSame(['Treatments', 'Patient history'], $type->theme['login_highlights']);
        Storage::disk('public')->assertExists($type->theme['login_image']);
        $this->assertSame(['customer' => 'Patient', 'customers' => 'Patients'], $type->terminology);
        $this->assertSame('Product', $type->resolvedTerminology()['product']);
        $this->assertTrue($type->hasModule('installments'));
        $this->assertSame(['purchases'], $type->resolvedModules()['hidden']);
    }

    public function test_invalid_colour_and_unknown_module_are_rejected(): void
    {
        $type = BusinessType::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.business-types.update', $type), [
            'name' => 'X',
            'theme' => ['primary_color' => 'green'],
            'modules' => ['enabled' => ['teleport']],
        ])->assertSessionHasErrors(['theme.primary_color', 'modules.enabled.0']);
    }
}
