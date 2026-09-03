<?php
namespace App\Http\Resources;
use App\Http\Resources\Concerns\ResolvesRelated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailResource extends JsonResource
{
    use ResolvesRelated;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'status' => $this->status,
            'error' => $this->error,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'to' => $this->to ?? [],
            'cc' => $this->cc ?? [],
            'bcc' => $this->bcc ?? [],
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'opens' => (int) $this->opens,
            'clicks' => (int) $this->clicks,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'account' => $this->whenLoaded('account', fn () => $this->account
                ? ['id' => $this->account->id, 'name' => $this->account->name, 'email_address' => $this->account->email_address] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size, 'url' => $a->url,
            ])),
            'related' => $this->relatedSummary(),
        ];
    }
}
