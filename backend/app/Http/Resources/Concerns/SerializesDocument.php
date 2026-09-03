<?php
namespace App\Http\Resources\Concerns;

/** Common header + line-item serialization for quotation/order/invoice resources. */
trait SerializesDocument
{
    protected function documentBase(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name,
                'customer_no' => $this->customer->customer_no,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner
                ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($it) => [
                'id' => $it->id, 'product_id' => $it->product_id, 'name' => $it->name,
                'description' => $it->description, 'quantity' => (float) $it->quantity,
                'unit_price' => (float) $it->unit_price, 'discount_pct' => (float) $it->discount_pct,
                'tax_rate_id' => $it->tax_rate_id, 'tax_amount' => (float) $it->tax_amount,
                'line_total' => (float) $it->line_total,
                'tax_rate' => $it->relationLoaded('taxRate') && $it->taxRate
                    ? ['id' => $it->taxRate->id, 'name' => $it->taxRate->name, 'rate' => (float) $it->taxRate->rate] : null,
            ])),
        ];
    }
}
