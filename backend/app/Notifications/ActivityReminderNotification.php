<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Reminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->reminder->title,
            'reminder_id' => $this->reminder->id,
            'related_type' => $this->reminder->related_type,
            'related_id' => $this->reminder->related_id,
            'url' => '/app/activities',
        ];
    }
}
