<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'stock_items_count' => $this->when(isset($this->stock_items_count), fn () => (int) $this->stock_items_count),
        ];
    }
}
