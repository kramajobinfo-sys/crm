<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\SerializesDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    use SerializesDocument;

    public function toArray(Request $request): array
    {
        return array_merge($this->documentBase(), [
            'order_no' => $this->order_no,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'quotation_id' => $this->quotation_id,
            'deal_id' => $this->deal_id,
            'converted_invoice_id' => $this->converted_invoice_id,
        ]);
    }
}
