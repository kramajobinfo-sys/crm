<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'unit' => $this->unit,
            'cost_price' => (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'barcode' => $this->barcode,
            'track_inventory' => (bool) $this->track_inventory,
            'reorder_level' => (float) $this->reorder_level,
            'is_active' => (bool) $this->is_active,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'parent_id' => $this->category->parent_id] : null),
            'tax_rate' => $this->whenLoaded('taxRate', fn () => $this->taxRate
                ? ['id' => $this->taxRate->id, 'name' => $this->taxRate->name, 'rate' => (float) $this->taxRate->rate] : null),
            'preferred_supplier' => $this->whenLoaded('preferredSupplier', fn () => $this->preferredSupplier
                ? ['vendor_id' => $this->preferredSupplier->vendor_id, 'vendor' => $this->preferredSupplier->vendor?->name, 'cost' => (float) $this->preferredSupplier->cost] : null),
            'suppliers' => $this->whenLoaded('suppliers', fn () => $this->suppliers->map(fn ($s) => [
                'id' => $s->id, 'vendor_id' => $s->vendor_id, 'vendor' => $s->vendor?->name,
                'supplier_sku' => $s->supplier_sku, 'cost' => (float) $s->cost,
                'lead_time_days' => $s->lead_time_days, 'currency' => $s->currency,
                'is_preferred' => (bool) $s->is_preferred,
            ])),
        ];
    }
}
