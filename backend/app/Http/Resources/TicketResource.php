<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'channel' => $this->channel,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'is_open' => $this->isOpen(),
            'is_breaching' => $this->isBreaching(),
            'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'due_human' => $this->due_at?->diffForHumans(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'reopened_count' => (int) $this->reopened_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name, 'customer_no' => $this->customer->customer_no,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'sla_policy' => $this->whenLoaded('slaPolicy', fn () => $this->slaPolicy ? [
                'id' => $this->slaPolicy->id, 'name' => $this->slaPolicy->name,
                'first_response_minutes' => $this->slaPolicy->first_response_minutes,
                'resolution_minutes' => $this->slaPolicy->resolution_minutes,
            ] : null),
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($r) => [
                'id' => $r->id, 'author_type' => $r->author_type, 'is_internal' => (bool) $r->is_internal,
                'body' => $r->body, 'user' => $r->user ? $r->user->name : null,
                'created_at' => $r->created_at?->toIso8601String(),
                'created_human' => $r->created_at?->diffForHumans(),
            ])),
            'escalations' => $this->whenLoaded('escalations', fn () => $this->escalations->map(fn ($e) => [
                'id' => $e->id, 'level' => $e->level, 'reason' => $e->reason, 'note' => $e->note,
                'to' => $e->assignee?->name, 'by' => $e->escalator?->name,
                'escalated_at' => $e->escalated_at?->toIso8601String(),
            ])),
        ];
    }
}
