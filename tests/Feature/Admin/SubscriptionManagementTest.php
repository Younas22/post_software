<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_trial_subscription_becomes_effectively_expired_once_trial_ends_at_has_passed(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        // Stored value is untouched — no job has run to update it — but the
        // reusable logic reports the true current state.
        $this->assertSame('trial', $subscription->status);
        $this->assertSame('expired', $subscription->effectiveStatus());
        $this->assertTrue($subscription->isExpired());
    }

    public function test_active_subscription_becomes_effectively_expired_once_ends_at_has_passed(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame('expired', $subscription->effectiveStatus());
    }

    public function test_suspended_and_cancelled_are_terminal_regardless_of_dates(): void
    {
        $suspended = Subscription::factory()->create(['status' => 'suspended', 'ends_at' => now()->addYear()]);
        $cancelled = Subscription::factory()->create(['status' => 'cancelled', 'ends_at' => now()->addYear()]);

        $this->assertSame('suspended', $suspended->effectiveStatus());
        $this->assertSame('cancelled', $cancelled->effectiveStatus());
    }

    public function test_active_trial_and_active_with_future_end_date_are_not_expired(): void
    {
        $trial = Subscription::factory()->create(['status' => 'trial', 'trial_ends_at' => now()->addDays(5)]);
        $active = Subscription::factory()->create(['status' => 'active', 'ends_at' => now()->addMonth()]);
        $activeNoEnd = Subscription::factory()->create(['status' => 'active', 'ends_at' => null]);

        $this->assertSame('trial', $trial->effectiveStatus());
        $this->assertSame('active', $active->effectiveStatus());
        $this->assertSame('active', $activeNoEnd->effectiveStatus());
    }

    public function test_super_admin_can_activate_suspend_and_cancel_a_subscription(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'trial']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.subscriptions.activate', $subscription))->assertRedirect();
        $this->assertSame('active', $subscription->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.subscriptions.suspend', $subscription))->assertRedirect();
        $this->assertSame('suspended', $subscription->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.subscriptions.cancel', $subscription))->assertRedirect();
        $fresh = $subscription->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_super_admin_can_extend_a_subscription(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'active', 'ends_at' => now()->addDays(3)]);
        $newDate = now()->addMonths(2)->toDateString();

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.extend', $subscription), ['ends_at' => $newDate])
            ->assertRedirect();

        $this->assertSame($newDate, $subscription->fresh()->ends_at->toDateString());
    }

    public function test_extend_rejects_a_past_date(): void
    {
        $subscription = Subscription::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.extend', $subscription), ['ends_at' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_super_admin_can_change_a_subscriptions_package(): void
    {
        $client = Client::factory()->create();
        $oldPackage = Package::factory()->create();
        $newPackage = Package::factory()->create();
        $subscription = Subscription::factory()->create(['client_id' => $client->id, 'package_id' => $oldPackage->id]);

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.change-package', $subscription), ['package_id' => $newPackage->id])
            ->assertRedirect();

        $this->assertSame($newPackage->id, $subscription->fresh()->package_id);
    }
}
