<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Device;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DeviceLimitReached;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsAndSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_notifies_users_of_a_subscription_expiring_soon(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $package = Package::factory()->create();
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'status' => 'active',
            'ends_at' => now()->addDays(2),
        ]);

        $this->artisan('platform:check-subscriptions-and-devices')->assertSuccessful();

        Notification::assertSentTo($user, SubscriptionExpiring::class);
    }

    public function test_command_marks_a_past_due_subscription_expired_and_notifies(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $this->artisan('platform:check-subscriptions-and-devices')->assertSuccessful();

        $this->assertSame('expired', $subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpired::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'subscription.expired', 'subject_id' => $subscription->id]);
    }

    public function test_command_notifies_a_client_at_their_device_limit(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $package = Package::factory()->create(['max_devices' => 1]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        $business = \App\Models\Business::factory()->create(['client_id' => $client->id]);
        $branch = \App\Models\Branch::factory()->create(['business_id' => $business->id]);
        Device::factory()->create(['client_id' => $client->id, 'business_id' => $business->id, 'branch_id' => $branch->id, 'status' => 'active']);

        $this->artisan('platform:check-subscriptions-and-devices')->assertSuccessful();

        Notification::assertSentTo($user, DeviceLimitReached::class);
    }

    public function test_command_does_not_notify_a_client_under_their_device_limit(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $package = Package::factory()->create(['max_devices' => 5]);
        Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);
        Device::factory()->create(['client_id' => $client->id, 'status' => 'active']);

        $this->artisan('platform:check-subscriptions-and-devices')->assertSuccessful();

        Notification::assertNotSentTo($user, DeviceLimitReached::class);
    }

    public function test_notifications_land_in_the_database_channel_for_the_admin_bell_dropdown(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $user->notify(new SubscriptionExpiring($subscription));

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }
}
