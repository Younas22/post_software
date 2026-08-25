<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\Client;
use App\Models\Package;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_logger_resolves_tenant_context_from_the_subject_not_the_actor(): void
    {
        $admin = $this->admin(); // no client_id of their own
        $client = Client::factory()->create();

        $log = app(ActivityLogger::class)->log('client.updated', $client, 'test', actor: $admin);

        $this->assertSame($client->id, $log->client_id);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_logger_falls_back_to_actor_tenant_when_subject_has_none(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);

        $log = app(ActivityLogger::class)->log('login', null, 'test', actor: $user);

        $this->assertSame($client->id, $log->client_id);
    }

    public function test_login_and_logout_are_logged(): void
    {
        $user = User::factory()->create(['is_super_admin' => true, 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id]);

        $this->actingAs($user)->post(route('logout'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'logout', 'user_id' => $user->id]);
    }

    public function test_client_lifecycle_actions_are_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.clients.store'), [
            'name' => 'Acme',
            'email' => 'acme@example.com',
            'status' => 'active',
            'owner_name' => 'Acme Owner',
            'owner_email' => 'owner@acme.example.com',
        ]);
        $client = Client::query()->where('email', 'acme@example.com')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['action' => 'client.created', 'subject_id' => $client->id, 'subject_type' => Client::class]);

        $this->actingAs($admin)->put(route('admin.clients.update', $client), [
            'name' => 'Acme Renamed',
            'email' => $client->email,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'client.updated', 'subject_id' => $client->id]);

        $this->actingAs($admin)->patch(route('admin.clients.suspend', $client));
        $this->assertDatabaseHas('activity_logs', ['action' => 'client.suspended', 'subject_id' => $client->id, 'client_id' => $client->id]);
    }

    public function test_package_and_subscription_and_device_actions_are_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'name' => 'Growth',
            'price' => 49,
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);
        $package = Package::query()->where('name', 'Growth')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['action' => 'package.created', 'subject_id' => $package->id]);

        $subscription = \App\Models\Subscription::factory()->create(['status' => 'trial']);
        $this->actingAs($admin)->patch(route('admin.subscriptions.activate', $subscription));
        $this->assertDatabaseHas('activity_logs', ['action' => 'subscription.activated', 'subject_id' => $subscription->id]);

        $device = \App\Models\Device::factory()->create(['status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.devices.block', $device));
        $this->assertDatabaseHas('activity_logs', ['action' => 'device.blocked', 'subject_id' => $device->id]);
    }

    public function test_business_settings_update_is_logged(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.businesses.settings.update', $business), [
            'tax_rate' => '5',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'settings.updated',
            'subject_id' => $business->id,
            'subject_type' => Business::class,
        ]);
    }

    public function test_activity_log_index_supports_search_and_action_filter(): void
    {
        ActivityLog::factory()->create(['action' => 'client.created', 'description' => 'Findable client made']);
        ActivityLog::factory()->create(['action' => 'device.blocked', 'description' => 'Some other action']);

        $response = $this->actingAs($this->admin())->get(route('admin.activity-logs.index', ['search' => 'Findable']));
        $response->assertOk()->assertSee('Findable client made')->assertDontSee('Some other action');

        $response = $this->actingAs($this->admin())->get(route('admin.activity-logs.index', ['action' => 'device.blocked']));
        $response->assertOk()->assertSee('Some other action')->assertDontSee('Findable client made');
    }
}
