<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_no' => $this->vendor_no,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'currency' => $this->currency,
            'payment_terms_days' => $this->payment_terms_days,
            'address' => $this->address,
            'contact_name' => $this->contact_name,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
