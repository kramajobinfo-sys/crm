<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reorder = (float) ($this->product->reorder_level ?? 0);
        $qty = (float) $this->quantity;
        return [
            'id' => $this->id,
            'quantity' => $qty,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'available' => $this->available,
            'average_cost' => (float) $this->average_cost,
            'bin_location' => $this->bin_location,
            'reorder_level' => $reorder,
            'is_low' => $reorder > 0 && $qty <= $reorder,
            'is_out' => $qty <= 0,
            // whenLoaded() is true once the relation has been eager-loaded even when it
            // resolved to null — which happens as soon as the product is soft-deleted. Laravel
            // turns the resulting "property on null" warning into an ErrorException, so the
            // whole stock list 500s. Guarded like every sibling resource does.
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id, 'sku' => $this->product->sku,
                'name' => $this->product->name, 'unit' => $this->product->unit,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id, 'name' => $this->warehouse->name, 'code' => $this->warehouse->code,
            ] : null),
        ];
    }
}
