<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeviceLimitReached extends Notification
{
    use Queueable;

    public function __construct(private readonly Client $client, private readonly int $limit)
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
        return [
            'type' => 'device.limit_reached',
            'client_id' => $this->client->id,
            'message' => "You've reached your package's device limit ({$this->limit}). Deactivate a device or upgrade your package to activate another.",
        ];
    }
}
