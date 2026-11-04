<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackagePriceCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_prices_use_the_selected_default_currency(): void
    {
        $this->seed([FeatureSeeder::class, PackageSeeder::class]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $package = Package::query()->firstOrFail();
        $package->update(['price' => 2500]);

        app(SettingsService::class)->set('default_currency', 'PKR');

        $this->actingAs($admin)->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSee('Rs 2,500.00')
            ->assertDontSee('$2,500.00');

        $this->actingAs($admin)->get(route('admin.packages.show', $package))->assertOk()->assertSee('Rs 2,500.00');
        $this->actingAs($admin)->get(route('admin.packages.create'))->assertOk()->assertSee('Price (PKR)');
    }
}
