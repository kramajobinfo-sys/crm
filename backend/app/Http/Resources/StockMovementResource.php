<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'balance_after' => (float) $this->balance_after,
            'reference' => $this->reference,
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'occurred_human' => $this->occurred_at?->diffForHumans(),
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse
                ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
        ];
    }
}
