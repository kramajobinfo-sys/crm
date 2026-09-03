<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BcEntityMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'crm_entity' => $this->crm_entity,
            'bc_entity' => $this->bc_entity,
            'direction' => $this->direction,
            'is_enabled' => (bool) $this->is_enabled,
            'field_map' => $this->field_map ?? [],
            'filter' => $this->filter ?? [],
            'interval_minutes' => (int) $this->interval_minutes,
            'sync_cursor' => $this->sync_cursor?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_run_human' => $this->last_run_at?->diffForHumans(),
            'links_count' => $this->whenCounted('links'),
        ];
    }
}
