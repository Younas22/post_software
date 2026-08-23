<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A generic, ad-hoc announcement — for a future Super Admin "broadcast a
 * message to a client" screen. Not wired to any UI yet; the notification
 * itself is the foundation Phase 8 asks for.
 */
class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $message)
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
            'type' => 'system',
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
