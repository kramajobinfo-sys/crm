<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\ResolvesRelated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    use ResolvesRelated;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_open' => $this->isOpen(),
            'is_overdue' => $this->isOverdue(),
            'due_at' => $this->due_at?->toIso8601String(),
            'due_human' => $this->due_at?->diffForHumans(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'related' => $this->relatedSummary(),
        ];
    }
}
