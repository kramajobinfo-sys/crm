<?php
namespace App\Http\Resources;
use App\Services\LeadScoringService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_no' => $this->lead_no,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'title' => $this->title,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'score' => (int) $this->score,
            'rating' => $this->rating,
            'priority' => $this->priority,
            'estimated_value' => (float) $this->estimated_value,
            'currency' => $this->currency,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'last_contacted_human' => $this->last_contacted_at?->diffForHumans(),
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'follow_up_human' => $this->follow_up_at?->diffForHumans(),
            'is_follow_up_overdue' => $this->follow_up_at ? $this->follow_up_at->isPast() && !$this->isConverted() : false,
            'next_action' => $this->next_action,
            'lost_reason' => $this->whenLoaded('lostReason', fn () => $this->lostReason
                ? ['id' => $this->lostReason->id, 'name' => $this->lostReason->name] : null),
            'lost_reason_id' => $this->lost_reason_id,
            'is_converted' => $this->isConverted(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'source' => $this->whenLoaded('source', fn () => $this->source
                ? ['id' => $this->source->id, 'name' => $this->source->name, 'code' => $this->source->code] : null),
            'status' => $this->whenLoaded('status', fn () => $this->status ? [
                'id' => $this->status->id, 'name' => $this->status->name, 'code' => $this->status->code,
                'color' => $this->status->color, 'is_won' => (bool) $this->status->is_won,
                'is_lost' => (bool) $this->status->is_lost,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner
                ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch
                ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name,
                'customer_no' => $this->customer->customer_no,
            ] : null),
            // Only on the detail view: shows why the score is what it is.
            'score_breakdown' => $this->when(
                $this->relationLoaded('status'),
                fn () => app(LeadScoringService::class)->evaluate($this->resource)['breakdown']
            ),
            'deals' => $this->whenLoaded('deals', fn () => $this->deals->map(fn ($d) => [
                'id' => $d->id, 'deal_no' => $d->deal_no, 'title' => $d->title,
                'amount' => (float) $d->amount, 'currency' => $d->currency, 'status' => $d->status,
                'stage' => $d->stage ? ['id' => $d->stage->id, 'name' => $d->stage->name] : null,
            ])),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'one_line' => $a->oneLine(),
            ])),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size,
                'kind' => $a->kind, 'url' => $a->url,
                'uploader' => $a->uploader ? $a->uploader->name : null,
            ])),
            'timeline' => $this->whenLoaded('timeline', fn () => $this->timeline->map(fn ($t) => [
                'id' => $t->id, 'type' => $t->type, 'title' => $t->title, 'body' => $t->body,
                'user' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
                'occurred_at' => $t->occurred_at?->toIso8601String(),
                'occurred_human' => $t->occurred_at?->diffForHumans(),
            ])),
        ];
    }
}
