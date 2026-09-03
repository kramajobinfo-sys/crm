<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\SerializesDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    use SerializesDocument;

    public function toArray(Request $request): array
    {
        return array_merge($this->documentBase(), [
            'quote_no' => $this->quote_no,
            'issue_date' => $this->issue_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'deal_id' => $this->deal_id,
            'converted_order_id' => $this->converted_order_id,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_name' => $this->signed_name,
            'signature_data' => $this->signature_data,
        ]);
    }
}
