<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'account' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'number' => $this->customer->customer_no,
                'name' => $this->customer->name,
                'type' => $this->customer->type,
                'status' => $this->customer->status,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ] : null),
            'name' => $this->name,
            'title' => $this->title,
            'department' => $this->department,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'is_primary' => (bool) $this->is_primary,
            'notes' => $this->notes,
            'portal_enabled' => (bool) $this->portal_enabled,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'label' => $a->label,
                'one_line' => $a->oneLine(),
            ])),
        ];
    }
}
