<?php
namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'customer_id'   => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
