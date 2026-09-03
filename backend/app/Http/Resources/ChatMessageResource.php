<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'content_type' => $this->content_type,
            'body' => $this->body,
            'status' => $this->status,
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_human' => $this->sent_at?->diffForHumans(),
            'sender' => $this->whenLoaded('sender', fn () => $this->sender
                ? ['id' => $this->sender->id, 'name' => $this->sender->name] : null),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size,
                'kind' => $a->kind,                       // image | video | audio | file
                'url' => $a->url, 'thumbnail_url' => $a->thumbnail_url,
                'width' => $a->width, 'height' => $a->height, 'duration_seconds' => $a->duration_seconds,
            ])),
        ];
    }
}
