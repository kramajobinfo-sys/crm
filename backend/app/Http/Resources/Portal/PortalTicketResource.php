<?php
namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Internal notes (is_internal=true) are never loaded/shown here — see PortalTicketController. */
class PortalTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'ticket_no'  => $this->ticket_no,
            'subject'    => $this->subject,
            'description'=> $this->description,
            'status'     => $this->status,
            'priority'   => $this->priority,
            'is_open'    => $this->isOpen(),
            'created_at' => $this->created_at?->toIso8601String(),
            'replies'    => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($r) => [
                'id' => $r->id, 'author_type' => $r->author_type, 'body' => $r->body,
                'created_at' => $r->created_at?->toIso8601String(),
            ])),
        ];
    }
}
