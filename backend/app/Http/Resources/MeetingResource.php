<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\ResolvesRelated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    use ResolvesRelated;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'start_human' => $this->start_at?->diffForHumans(),
            'organizer' => $this->whenLoaded('organizer', fn () => $this->organizer
                ? ['id' => $this->organizer->id, 'name' => $this->organizer->name] : null),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($p) => [
                'id' => $p->id, 'user_id' => $p->user_id, 'name' => $p->display_name,
                'email' => $p->email, 'response' => $p->response,
            ])),
            'related' => $this->relatedSummary(),
        ];
    }
}
