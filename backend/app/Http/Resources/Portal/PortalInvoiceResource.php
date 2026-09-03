<?php
namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'invoice_no'  => $this->invoice_no,
            'status'      => $this->status,
            'issue_date'  => $this->issue_date,
            'due_date'    => $this->due_date,
            'currency'    => $this->currency,
            'grand_total' => $this->grand_total,
            'amount_paid' => $this->amount_paid,
            'balance'     => $this->balance,
            'is_overdue'  => $this->isOverdue(),
            'items'       => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'name' => $i->name, 'description' => $i->description, 'quantity' => $i->quantity,
                'unit_price'  => $i->unit_price, 'line_total' => $i->line_total,
            ])),
        ];
    }
}
