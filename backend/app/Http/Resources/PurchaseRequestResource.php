<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\SerializesApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    use SerializesApproval;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pr_no' => $this->pr_no,
            'status' => $this->status,
            'needed_by' => $this->needed_by?->toDateString(),
            'estimated_total' => (float) $this->estimated_total,
            'notes' => $this->notes,
            'converted_po_id' => $this->converted_po_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester
                ? ['id' => $this->requester->id, 'name' => $this->requester->name] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($it) => [
                'id' => $it->id, 'product_id' => $it->product_id, 'name' => $it->name,
                'quantity' => (float) $it->quantity, 'estimated_price' => (float) $it->estimated_price,
                'note' => $it->note,
            ])),
            'approval' => $this->approvalSummary(),
        ];
    }
}
