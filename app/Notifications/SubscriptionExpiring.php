<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification
{
    use Queueable;

    public function __construct(private readonly Subscription $subscription)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $endsAt = $this->subscription->ends_at ?? $this->subscription->trial_ends_at;
        $packageName = $this->subscription->package?->name ?? 'your plan';

        return [
            'type' => 'subscription.expiring',
            'subscription_id' => $this->subscription->id,
            'message' => "Your subscription ({$packageName}) expires on ".$endsAt?->format('M j, Y').'.',
        ];
    }
}
