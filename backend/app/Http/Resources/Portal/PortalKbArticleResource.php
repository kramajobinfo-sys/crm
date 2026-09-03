<?php
namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing article view. Deliberately omits status/visibility/author/internal fields —
 * only ever populated from a query already filtered to published + public (PortalKbController).
 * `body` is null in the list (that query doesn't select it) and present on the detail.
 */
class PortalKbArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'slug'     => $this->slug,
            'excerpt'  => $this->excerpt,
            'body'     => $this->body,
            'view_count' => (int) $this->view_count,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
