<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\SerializesApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    use SerializesApproval;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_no' => $this->po_no,
            'status' => $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'received_at' => $this->received_at?->toIso8601String(),
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'purchase_request_id' => $this->purchase_request_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor
                ? ['id' => $this->vendor->id, 'name' => $this->vendor->name, 'vendor_no' => $this->vendor->vendor_no ?? null] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse
                ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($it) => [
                'id' => $it->id, 'product_id' => $it->product_id, 'name' => $it->name,
                'description' => $it->description, 'quantity' => (float) $it->quantity,
                'received_quantity' => (float) $it->received_quantity, 'outstanding' => $it->outstanding,
                'unit_price' => (float) $it->unit_price, 'discount_pct' => (float) $it->discount_pct,
                'tax_rate_id' => $it->tax_rate_id, 'tax_amount' => (float) $it->tax_amount,
                'line_total' => (float) $it->line_total,
            ])),
            'approval' => $this->approvalSummary(),
        ];
    }
}
