<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'tags' => $this->tags ?? [],
            'unread_count' => (int) $this->unread_count,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_human' => $this->last_message_at?->diffForHumans(),
            'service_window_open' => $this->serviceWindowOpen(),
            'channel' => $this->whenLoaded('channel', fn () => [
                'id' => $this->channel->id, 'type' => $this->channel->type, 'name' => $this->channel->name,
            ]),
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id, 'name' => $this->contact->display_name,
                'avatar_url' => $this->contact->avatar_url,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
