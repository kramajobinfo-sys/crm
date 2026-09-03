<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceBookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'entries_count' => $this->whenCounted('entries'),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($e) => [
                'id' => $e->id,
                'product_id' => $e->product_id,
                'unit_price' => (float) $e->unit_price,
                'product' => $e->relationLoaded('product') && $e->product ? [
                    'id' => $e->product->id,
                    'sku' => $e->product->sku,
                    'name' => $e->product->name,
                    'sale_price' => (float) $e->product->sale_price,
                ] : null,
            ])),
        ];
    }
}
