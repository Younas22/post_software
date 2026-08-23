<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Subscription;
use App\Notifications\DeviceLimitReached;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiring;
use App\Services\ActivityLogger;
use App\Services\DeviceActivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily sweep (see routes/console.php for the schedule) that:
 *   1. Persists `expired` onto any subscription whose effective status has
 *      moved past its stored one — closing the gap Subscription::
 *      effectiveStatus() exists to cover until this runs.
 *   2. Notifies each affected client's users of expiring/expired
 *      subscriptions and any client at its device limit.
 *
 * No de-duplication tracking exists yet (see class-level TODO note) — this
 * runs are idempotent for state changes, but repeat runs will re-notify.
 */
class CheckSubscriptionsAndDevices extends Command
{
    protected $signature = 'platform:check-subscriptions-and-devices {--days=3 : How many days ahead counts as "expiring soon"}';

    protected $description = 'Notify clients of expiring/expired subscriptions and devices at their package limit, persisting expired status where due.';

    public function handle(DeviceActivationService $devices, ActivityLogger $logger): int
    {
        $days = (int) $this->option('days');

        $this->checkExpiring($days);
        $this->checkNewlyExpired($logger);
        $this->checkDeviceLimits($devices);

        return self::SUCCESS;
    }

    private function checkExpiring(int $days): void
    {
        $subscriptions = Subscription::query()
            ->with(['client.users', 'package'])
            ->whereIn('status', ['trial', 'active'])
            ->where(function ($query) use ($days) {
                $query->whereBetween('ends_at', [now(), now()->addDays($days)])
                    ->orWhereBetween('trial_ends_at', [now(), now()->addDays($days)]);
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $users = $subscription->client?->users ?? collect();

            if ($users->isNotEmpty()) {
                Notification::send($users, new SubscriptionExpiring($subscription));
            }
        }

        $this->info("Notified {$subscriptions->count()} expiring subscription(s).");
    }

    private function checkNewlyExpired(ActivityLogger $logger): void
    {
        $subscriptions = Subscription::query()
            ->with(['client.users', 'package'])
            ->whereIn('status', ['trial', 'active'])
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->isExpired());

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);

            $logger->log(
                'subscription.expired',
                $subscription,
                'Subscription automatically marked expired',
                actor: null,
            );

            $users = $subscription->client?->users ?? collect();

            if ($users->isNotEmpty()) {
                Notification::send($users, new SubscriptionExpired($subscription));
            }
        }

        $this->info("Marked {$subscriptions->count()} subscription(s) as expired.");
    }

    private function checkDeviceLimits(DeviceActivationService $devices): void
    {
        $notified = 0;

        Client::query()->where('status', 'active')->with('users')->chunk(100, function ($clients) use ($devices, &$notified) {
            foreach ($clients as $client) {
                $limit = $devices->limitFor($client);

                if ($limit === null) {
                    continue;
                }

                if ($devices->activeCount($client) >= $limit && $client->users->isNotEmpty()) {
                    Notification::send($client->users, new DeviceLimitReached($client, $limit));
                    $notified++;
                }
            }
        });

        $this->info("Notified {$notified} client(s) at their device limit.");
    }
}
