<?php
namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalQuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'quote_no'     => $this->quote_no,
            'status'       => $this->status,
            'issue_date'   => $this->issue_date,
            'valid_until'  => $this->valid_until,
            'currency'     => $this->currency,
            'grand_total'  => $this->grand_total,
            'signed_at'    => $this->signed_at,
            'signed_name'  => $this->signed_name,
            'signature_data' => $this->signature_data,
            'items'        => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'name' => $i->name, 'description' => $i->description, 'quantity' => $i->quantity,
                'unit_price' => $i->unit_price, 'line_total' => $i->line_total,
            ])),
        ];
    }
}
