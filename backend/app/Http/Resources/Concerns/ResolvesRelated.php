<?php
namespace App\Http\Resources\Concerns;

use App\Services\ActivityService;

/**
 * Summarise the polymorphic `related` record (deal / lead / customer) into a
 * small, uniform shape the UI can render as a link, whatever the concrete type.
 */
trait ResolvesRelated
{
    protected function relatedSummary(): ?array
    {
        if (!$this->related_type || !$this->related_id) return null;
        $r = $this->related;   // eager-loaded by the service
        if (!$r) return null;

        $alias = array_search($this->related_type, ActivityService::RELATED_MAP, true) ?: class_basename($this->related_type);

        return [
            'type'  => $alias,
            'id'    => $r->getKey(),
            // `name` before `title`: a Lead's `title` is a job title, its `name` is the
            // person; a Deal has no `name` so it falls through to its `title` (the deal name).
            'label' => $r->name ?? $r->title ?? ('#'.$r->getKey()),
            'ref'   => $r->deal_no ?? $r->lead_no ?? $r->customer_no ?? null,
        ];
    }
}
