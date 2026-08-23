<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\FeatureAccessService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAndFeatureArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_resolve_cascades_from_branch_to_business_to_client_to_system_to_default(): void
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);
        $service = app(SettingsService::class);

        // Nothing set anywhere yet — falls all the way to the registry default.
        $this->assertSame('INV-', $service->resolve('invoice_prefix', branch: $branch, business: $business, client: $client));

        // Set at business level — branch (which has no override) inherits it.
        $service->set('invoice_prefix', 'ACME-', business: $business);
        $this->assertSame('ACME-', $service->resolve('invoice_prefix', branch: $branch, business: $business, client: $client));

        // A branch-level override wins over the business-level value.
        $service->set('invoice_prefix', 'MAIN-', branch: $branch);
        $this->assertSame('MAIN-', $service->resolve('invoice_prefix', branch: $branch, business: $business, client: $client));

        // Querying at the business level (no branch given) still gets the
        // business-level value, unaffected by the branch-level override.
        $this->assertSame('ACME-', $service->resolve('invoice_prefix', business: $business, client: $client));
    }

    public function test_settings_registry_filters_by_scope(): void
    {
        $service = app(SettingsService::class);

        $businessKeys = array_keys($service->keysForScope('business'));
        $systemKeys = array_keys($service->keysForScope('system'));

        $this->assertContains('invoice_prefix', $businessKeys);
        $this->assertNotContains('invoice_prefix', $systemKeys);
        $this->assertContains('app_name', $systemKeys);
    }

    public function test_feature_access_falls_back_to_the_clients_package(): void
    {
        $pos = Feature::factory()->create(['slug' => 'pos']);
        $package = Package::factory()->create();
        $package->features()->attach($pos);

        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);

        $service = app(FeatureAccessService::class);

        $this->assertTrue($service->isEnabled($business, 'pos'));
        $this->assertFalse($service->isEnabled($business, 'nonexistent-feature'));
    }

    public function test_business_override_disables_a_feature_the_package_grants(): void
    {
        $multiBranch = Feature::factory()->create(['slug' => 'multi-branch']);
        $package = Package::factory()->create();
        $package->features()->attach($multiBranch);

        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);

        $service = app(FeatureAccessService::class);
        $this->assertTrue($service->isEnabled($business, 'multi-branch'));

        $business->featureOverrides()->attach($multiBranch->id, ['enabled' => false]);

        $this->assertFalse($service->isEnabled($business, 'multi-branch'));
    }

    public function test_business_override_enables_a_feature_the_package_does_not_grant(): void
    {
        $restaurantModule = Feature::factory()->create(['slug' => 'restaurant-module']);
        $package = Package::factory()->create(); // does not include the feature

        $client = Client::factory()->create();
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = Business::factory()->create(['client_id' => $client->id]);

        $service = app(FeatureAccessService::class);
        $this->assertFalse($service->isEnabled($business, 'restaurant-module'));

        $business->featureOverrides()->attach($restaurantModule->id, ['enabled' => true]);

        $this->assertTrue($service->isEnabled($business, 'restaurant-module'));
    }

    public function test_feature_access_is_false_with_no_subscription_at_all(): void
    {
        $business = Business::factory()->create();

        $this->assertFalse(app(FeatureAccessService::class)->isEnabled($business, 'pos'));
    }
}
