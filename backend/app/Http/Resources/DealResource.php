<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deal_no' => $this->deal_no,
            'title' => $this->title,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'probability' => (int) $this->probability,
            'weighted_amount' => $this->weighted_amount,
            'status' => $this->status,
            'source' => $this->source,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'won_at' => $this->won_at?->toIso8601String(),
            'lost_at' => $this->lost_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'pipeline' => $this->whenLoaded('pipeline', fn () => $this->pipeline
                ? ['id' => $this->pipeline->id, 'name' => $this->pipeline->name] : null),
            'stage' => $this->whenLoaded('stage', fn () => $this->stage ? [
                'id' => $this->stage->id, 'name' => $this->stage->name, 'color' => $this->stage->color,
                'is_won' => (bool) $this->stage->is_won, 'is_lost' => (bool) $this->stage->is_lost,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner
                ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch
                ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name,
                'customer_no' => $this->customer->customer_no,
            ] : null),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id, 'name' => $this->lead->name, 'lead_no' => $this->lead->lead_no,
            ] : null),
            'lost_reason' => $this->whenLoaded('lostReason', fn () => $this->lostReason
                ? ['id' => $this->lostReason->id, 'name' => $this->lostReason->name] : null),
            'project' => $this->whenLoaded('project', fn () => $this->project
                ? ['id'=>$this->project->id,'project_no'=>$this->project->project_no,'name'=>$this->project->name,'status'=>$this->project->status] : null),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => [
                'id' => $p->id, 'product_id' => $p->product_id, 'name' => $p->name,
                'description' => $p->description, 'quantity' => (float) $p->quantity,
                'unit_price' => (float) $p->unit_price, 'discount_pct' => (float) $p->discount_pct,
                'line_total' => (float) $p->line_total,
            ])),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'name' => $contact->name,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'role' => $contact->pivot->role,
                'is_primary' => (bool) $contact->pivot->is_primary,
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
