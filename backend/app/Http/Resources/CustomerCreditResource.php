<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_no' => $this->credit_no,
            'source' => $this->source,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'applied_amount' => (float) $this->applied_amount,
            'remaining' => $this->remaining,
            'reason' => $this->reason,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'name' => $this->customer->name,
                'customer_no' => $this->customer->customer_no,
            ] : null),
            'source_invoice' => $this->whenLoaded('sourceInvoice', fn () => $this->sourceInvoice ? [
                'id' => $this->sourceInvoice->id, 'invoice_no' => $this->sourceInvoice->invoice_no,
            ] : null),
            'source_payment' => $this->whenLoaded('sourcePayment', fn () => $this->sourcePayment ? [
                'id' => $this->sourcePayment->id, 'payment_no' => $this->sourcePayment->payment_no,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'applications' => $this->whenLoaded('applications', fn () => $this->applications->map(fn ($a) => [
                'id' => $a->id,
                'amount' => (float) $a->amount,
                'applied_at' => $a->applied_at?->toIso8601String(),
                'invoice' => $a->invoice ? ['id' => $a->invoice->id, 'invoice_no' => $a->invoice->invoice_no] : null,
            ])),
        ];
    }
}
