<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Staff-side article resource — exposes status/visibility/author. Never used on the portal. */
class KbArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'excerpt' => $this->excerpt,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'view_count' => (int) $this->view_count,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'author' => $this->whenLoaded('author', fn () => $this->author
                ? ['id' => $this->author->id, 'name' => $this->author->name] : null),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_human' => $this->updated_at?->diffForHumans(),
        ];
    }
}
