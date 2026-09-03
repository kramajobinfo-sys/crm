<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'subject' => $this->subject,
            'body' => $this->body,
            'audience' => $this->audience,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'recipients_count' => (int) $this->recipients_count,
            'sent_count' => (int) $this->sent_count,
            'opened_count' => (int) $this->opened_count,
            'clicked_count' => (int) $this->clicked_count,
            'failed_count' => (int) $this->failed_count,
            'open_rate' => $this->open_rate,
            'is_editable' => $this->isEditable(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at?->diffForHumans(),
            'template' => $this->whenLoaded('template', fn () => $this->template
                ? ['id' => $this->template->id, 'name' => $this->template->name] : null),
            'email_account' => $this->whenLoaded('emailAccount', fn () => $this->emailAccount
                ? ['id' => $this->emailAccount->id, 'name' => $this->emailAccount->name] : null),
            'sms_provider' => $this->whenLoaded('smsProvider', fn () => $this->smsProvider
                ? ['id' => $this->smsProvider->id, 'name' => $this->smsProvider->name] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
        ];
    }
}
