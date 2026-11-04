<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LocaleOptionsService;
use App\Services\SettingsService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Super Admin chooses which currencies/timezones are offered (System
 * Settings); clients may only pick from that set via the API.
 */
class LocaleOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class, FeatureSeeder::class, PackageSeeder::class]);
    }

    private function actingAsApi(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken);
    }

    /** @return array{0: Business, 1: User} */
    private function ownerSetup(): array
    {
        $client = Client::factory()->create();
        $package = Package::query()->where('slug', 'starter')->firstOrFail();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'trial']);

        $business = Business::factory()->create(['client_id' => $client->id, 'currency' => 'USD', 'timezone' => 'UTC']);
        Branch::factory()->create(['business_id' => $business->id, 'is_main' => true]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$business, $owner];
    }

    public function test_super_admin_can_enable_currencies_and_timezones(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Currencies');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            '_locale_section' => '1',
            'supported_currencies' => ['PKR', 'AED'],
            'supported_timezones' => ['Asia/Karachi'],
            'default_currency' => 'USD',
            'default_timezone' => 'UTC',
        ])->assertRedirect(route('admin.settings.index'))->assertSessionHasNoErrors();

        $locale = app(LocaleOptionsService::class);
        // The default is always folded into the enabled set.
        $this->assertEqualsCanonicalizing(['USD', 'PKR', 'AED'], $locale->enabledCurrencyCodes());
        $this->assertEqualsCanonicalizing(['UTC', 'Asia/Karachi'], $locale->enabledTimezoneIds());
    }

    public function test_unknown_currency_is_rejected_by_admin_settings(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            '_locale_section' => '1',
            'supported_currencies' => ['XXX'],
            'supported_timezones' => ['UTC'],
            'default_currency' => 'USD',
            'default_timezone' => 'UTC',
        ])->assertSessionHasErrors('supported_currencies.0');
    }

    public function test_client_gets_only_enabled_options_and_can_switch_between_them(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('supported_currencies', json_encode(['USD', 'PKR']));
        $settings->set('supported_timezones', json_encode(['UTC', 'Asia/Karachi']));

        [$business, $owner] = $this->ownerSetup();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/business/locale-options')->assertOk();
        $this->assertEqualsCanonicalizing(['USD', 'PKR'], array_column($response->json('data.currencies'), 'code'));
        $this->assertEqualsCanonicalizing(['UTC', 'Asia/Karachi'], array_column($response->json('data.timezones'), 'id'));

        $this->actingAsApi($owner)->putJson('/api/v1/business', [
            'name' => $business->name, 'currency' => 'PKR', 'timezone' => 'Asia/Karachi',
        ])->assertOk();

        $this->assertSame('PKR', $business->fresh()->currency);
        $this->assertSame('Asia/Karachi', $business->fresh()->timezone);

        // A currency the admin hasn't enabled is refused.
        $this->actingAsApi($owner)->putJson('/api/v1/business', [
            'name' => $business->name, 'currency' => 'EUR', 'timezone' => 'Asia/Karachi',
        ])->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_config_exposes_absolute_logo_url(): void
    {
        Storage::fake('public');
        [$business, $owner] = $this->ownerSetup();
        $business->update(['logo' => 'business-logos/logo.png']);

        $url = $this->actingAsApi($owner)->getJson('/api/v1/config')->assertOk()->json('data.business.logo_url');

        $this->assertSame(Storage::disk('public')->url('business-logos/logo.png'), $url);
    }
}
