<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every string here originated in a browser. It is returned as plain data and the UI renders
 * it as text only — never v-html.
 */
class WebVisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visitor_uid' => $this->visitor_uid,
            'is_identified' => $this->isIdentified(),
            'first_landing_url' => $this->first_landing_url,
            'first_referrer' => $this->first_referrer,
            'user_agent' => $this->user_agent,
            'page_view_count' => (int) $this->page_view_count,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'identified_at' => $this->identified_at?->toIso8601String(),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id, 'lead_no' => $this->lead->lead_no,
                'name' => $this->lead->name, 'email' => $this->lead->email,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id, 'customer_no' => $this->customer->customer_no,
                'name' => $this->customer->name, 'email' => $this->customer->email,
            ] : null),
            'page_views' => $this->whenLoaded('pageViews', fn () => $this->pageViews->map(fn ($v) => [
                'id' => $v->id, 'url' => $v->url, 'path' => $v->path, 'title' => $v->title,
                'referrer' => $v->referrer, 'occurred_at' => $v->occurred_at?->toIso8601String(),
            ])),
            // ip_hash is deliberately NOT exposed: it is an abuse-grouping key, not something
            // the UI needs, and surfacing it invites treating it as an identifier.
        ];
    }
}
