<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\ResolvesRelated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    use ResolvesRelated;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'remind_at' => $this->remind_at?->toIso8601String(),
            'remind_human' => $this->remind_at?->diffForHumans(),
            'channel' => $this->channel,
            'is_sent' => (bool) $this->is_sent,
            'related' => $this->relatedSummary(),
        ];
    }
}
