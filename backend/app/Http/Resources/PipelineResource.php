<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'color' => $s->color,
                'order_index' => (int) $s->order_index, 'probability' => (int) $s->probability,
                'is_won' => (bool) $s->is_won, 'is_lost' => (bool) $s->is_lost,
                'required_fields' => $s->required_fields ?? [],
                'allowed_next_stage_ids' => $s->allowed_next_stage_ids ?? [],
            ])),
        ];
    }
}
