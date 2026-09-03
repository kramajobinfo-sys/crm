<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'received_at' => $this->received_at?->toIso8601String(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice
                ? ['id' => $this->invoice->id, 'invoice_no' => $this->invoice->invoice_no] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name,
                'customer_no' => $this->customer->customer_no,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
        ];
    }
}
