<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app CRM notification (database channel → surfaces in the notification bell).
 * A single flexible class keeps the many small CRM events from each needing a bespoke
 * Notification subclass; the specific event is carried in `type`, the display text in
 * `message`, and the click-through target in `url`. Queued so a trigger never blocks the
 * request it fires from.
 */
class CrmNotification extends Notification
{
    use Queueable;

    /**
     * @param string      $event   dotted event key, e.g. "lead.assigned"
     * @param string      $message human-readable line shown in the bell
     * @param string|null $url     in-app router path to open on click, e.g. "/app/leads"
     * @param array       $extra   any extra ids/fields to persist with the notification
     */
    public function __construct(
        public string $event,
        public string $message,
        public ?string $url = null,
        public array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type' => $this->event,
            'message' => $this->message,
            'url' => $this->url,
        ], $this->extra);
    }
}
