<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\ResolvesRelated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallResource extends JsonResource
{
    use ResolvesRelated;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'direction' => $this->direction,
            'status' => $this->status,
            'phone' => $this->phone,
            'duration_seconds' => (int) $this->duration_seconds,
            'duration_human' => $this->duration_human,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'occurred_human' => $this->occurred_at?->diffForHumans(),
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'related' => $this->relatedSummary(),
        ];
    }
}
