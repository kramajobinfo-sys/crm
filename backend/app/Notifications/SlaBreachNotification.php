<?php
namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app alert to a ticket's assignee when its first-response or resolution SLA is breached.
 * Database channel (surfaces in the notification bell); queued so the sweep never blocks on it.
 */
class SlaBreachNotification extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->kind === 'response' ? 'First-response SLA breached' : 'Resolution SLA breached';
        return [
            'type' => 'ticket.sla_breach',
            'kind' => $this->kind,
            'ticket_id' => $this->ticket->id,
            'ticket_no' => $this->ticket->ticket_no,
            'message' => "{$label}: {$this->ticket->ticket_no} — {$this->ticket->subject}",
            'url' => "/helpdesk?ticket={$this->ticket->id}",
        ];
    }
}
