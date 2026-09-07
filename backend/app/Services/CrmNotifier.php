<?php
namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Notifications\Notification;

/**
 * Central dispatcher for in-app CRM notifications. Keeps recipient resolution and the
 * "don't notify yourself" guard in one place so triggers scattered across the services
 * stay one-liners. Recipients are resolved without the company global scope because some
 * triggers (the scheduled follow-up sweep) run in a console context with no auth user.
 */
class CrmNotifier
{
    /**
     * Deliver a notification to one user id. No-ops on a null/invalid recipient, and — unless
     * $skipSelf is false — on the acting user, who already knows about the change they just made.
     */
    public function toUser(?int $userId, Notification $notification, bool $skipSelf = true): void
    {
        if (!$userId) return;
        if ($skipSelf && auth()->check() && (int) $userId === (int) auth()->id()) return;

        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->find($userId);
        $user?->notify($notification);
    }

    public function leadAssigned(Lead $lead): void
    {
        $this->toUser($lead->owner_id, new CrmNotification(
            'lead.assigned',
            "Lead assigned to you: {$lead->lead_no} — {$lead->name}",
            '/app/leads',
            ['lead_id' => $lead->id],
        ));
    }

    /** $outcome is "won" or "lost". */
    public function dealClosed(Deal $deal, string $outcome): void
    {
        $this->toUser($deal->owner_id, new CrmNotification(
            "deal.{$outcome}",
            ucfirst($outcome)." deal: {$deal->deal_no} — {$deal->title}",
            '/app/deals',
            ['deal_id' => $deal->id],
        ));
    }

    public function ticketAssigned(Ticket $ticket): void
    {
        $this->toUser($ticket->assigned_to, new CrmNotification(
            'ticket.assigned',
            "Ticket assigned to you: {$ticket->ticket_no} — {$ticket->subject}",
            '/app/helpdesk',
            ['ticket_id' => $ticket->id],
        ));
    }

    /** A lead whose follow-up date has arrived. Fired from the console sweep (no self-skip). */
    public function leadFollowUpDue(Lead $lead): void
    {
        $this->toUser($lead->owner_id, new CrmNotification(
            'lead.follow_up_due',
            "Follow-up due: {$lead->lead_no} — {$lead->name}",
            '/app/leads',
            ['lead_id' => $lead->id],
        ), skipSelf: false);
    }
}
