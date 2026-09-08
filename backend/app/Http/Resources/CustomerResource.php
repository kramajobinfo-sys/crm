<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_no' => $this->customer_no,
            'type' => $this->type,
            'status' => $this->status,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'territory' => $this->territory,
            'tags' => $this->tags ?? [],
            'currency' => $this->currency,
            'price_book_id' => $this->price_book_id,
            'price_book' => $this->whenLoaded('priceBook', fn () => $this->priceBook
                ? ['id' => $this->priceBook->id, 'name' => $this->priceBook->name, 'currency' => $this->priceBook->currency] : null),
            'credit_limit' => (float) $this->credit_limit,
            'payment_terms_days' => $this->payment_terms_days,
            'effective_payment_terms' => $this->effectivePaymentTerms(),
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields ?? new \stdClass(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'contacts_count' => $this->whenCounted('contacts'),
            'group' => $this->whenLoaded('group', fn () => $this->group
                ? ['id' => $this->group->id, 'name' => $this->group->name, 'code' => $this->group->code] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner
                ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch
                ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'label' => $a->label,
                'line1' => $a->line1, 'line2' => $a->line2, 'city' => $a->city,
                'state' => $a->state, 'postal_code' => $a->postal_code, 'country' => $a->country,
                'is_default' => (bool) $a->is_default, 'one_line' => $a->oneLine(),
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
