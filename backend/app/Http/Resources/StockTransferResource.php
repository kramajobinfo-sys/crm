<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_no' => $this->transfer_no,
            'status' => $this->status,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'notes' => $this->notes,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'items_count' => $this->when(isset($this->items_count), fn () => (int) $this->items_count),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => $this->fromWarehouse
                ? ['id' => $this->fromWarehouse->id, 'name' => $this->fromWarehouse->name, 'code' => $this->fromWarehouse->code] : null),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => $this->toWarehouse
                ? ['id' => $this->toWarehouse->id, 'name' => $this->toWarehouse->name, 'code' => $this->toWarehouse->code] : null),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($it) => [
                'id' => $it->id, 'product_id' => $it->product_id, 'name' => $it->name,
                'quantity' => (float) $it->quantity,
                'sku' => $it->relationLoaded('product') && $it->product ? $it->product->sku : null,
            ])),
        ];
    }
}
