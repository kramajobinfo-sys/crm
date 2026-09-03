<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'build_no' => $this->build_no,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'status' => $this->status,
            'notes' => $this->notes,
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse
                ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'builder' => $this->whenLoaded('builder', fn () => $this->builder
                ? ['id' => $this->builder->id, 'name' => $this->builder->name] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($it) => [
                'component_product_id' => $it->component_product_id,
                'name' => $it->name,
                'sku' => $it->component?->sku,
                'quantity' => (float) $it->quantity,
                'unit_cost' => (float) $it->unit_cost,
            ])),
            'built_at' => $this->built_at?->toIso8601String(),
            'built_human' => $this->built_at?->diffForHumans(),
        ];
    }
}
