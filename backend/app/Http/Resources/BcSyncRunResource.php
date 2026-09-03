<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BcSyncRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'mapping_id' => $this->mapping_id,
            'direction' => $this->direction,
            'trigger' => $this->trigger,
            'status' => $this->status,
            'counts' => [
                'created' => $this->created_count, 'updated' => $this->updated_count,
                'skipped' => $this->skipped_count, 'failed'  => $this->failed_count,
            ],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->durationSeconds(),
            'started_human' => $this->started_at?->diffForHumans(),
            'error' => $this->error,
            'mapping' => $this->whenLoaded('mapping', fn () => $this->mapping ? [
                'id' => $this->mapping->id,
                'crm_entity' => $this->mapping->crm_entity,
                'bc_entity' => $this->mapping->bc_entity,
            ] : null),
            'issues' => $this->whenLoaded('issues', fn () => $this->issues->map(fn ($i) => [
                'id' => $i->id, 'stage' => $i->stage, 'severity' => $i->severity,
                'message' => $i->message, 'created_at' => $i->created_at?->toIso8601String(),
            ])),
        ];
    }
}
