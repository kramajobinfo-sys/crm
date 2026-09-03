<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\SerializesDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    use SerializesDocument;

    public function toArray(Request $request): array
    {
        return array_merge($this->documentBase(), [
            'invoice_no' => $this->invoice_no,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) $this->balance,
            'is_overdue' => $this->isOverdue(),
            'sales_order_id' => $this->sales_order_id,
            'order_no' => $this->whenLoaded('order', fn () => $this->order?->order_no),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'payment_no' => $p->payment_no, 'method' => $p->method,
                'amount' => (float) $p->amount, 'received_at' => $p->received_at?->toIso8601String(),
                'reference' => $p->reference,
                'creator' => $p->creator ? $p->creator->name : null,
            ])),
        ]);
    }
}
