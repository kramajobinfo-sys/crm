<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'entity' => $this->entity,
            'trigger_type' => $this->trigger_type,
            'trigger_event' => $this->trigger_event,
            'conditions' => $this->conditions,
            'schedule_cron' => $this->schedule_cron,
            'is_active' => (bool) $this->is_active,
            'run_count' => (int) $this->run_count,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_run_human' => $this->last_run_at?->diffForHumans(),
            'actions_count' => $this->when(isset($this->actions_count), fn () => (int) $this->actions_count),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'actions' => $this->whenLoaded('actions', fn () => $this->actions->map(fn ($a) => [
                'id' => $a->id, 'order' => $a->order, 'type' => $a->type, 'config' => $a->config,
            ])),
            'runs' => $this->whenLoaded('runs', fn () => $this->runs->map(fn ($r) => [
                'id' => $r->id, 'status' => $r->status, 'trigger_type' => $r->trigger_type,
                'actions_run' => $r->actions_run, 'log' => $r->log,
                'triggered_by' => $r->triggeredBy?->name,
                'created_at' => $r->created_at?->toIso8601String(),
                'created_human' => $r->created_at?->diffForHumans(),
            ])),
        ];
    }
}
