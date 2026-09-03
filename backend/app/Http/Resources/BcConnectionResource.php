<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whitelist only. client_secret is never included — it is also `hidden` and
 * `encrypted` on the model, so a leak needs three independent mistakes.
 */
class BcConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'environment' => $this->environment,
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->client_id,
            'has_secret' => filled($this->credentials()['client_secret']),
            'credential_source' => $this->credentialSource(),   // env | database | none
            'bc_company_id' => $this->bc_company_id,
            'bc_company_name' => $this->bc_company_name,
            'base_url' => $this->base_url,
            'api_version' => $this->api_version,
            'is_active' => (bool) $this->is_active,
            'is_configured' => $this->isConfigured(),
            'status' => $this->status,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'mappings' => BcEntityMappingResource::collection($this->whenLoaded('mappings')),
        ];
    }
}
