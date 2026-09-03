<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Deliberately NO url field — documents are private; download only via the streaming endpoint. */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'mime' => $this->mime,
            'kind' => $this->kind,
            'size' => (int) $this->size,
            'folder_id' => $this->folder_id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder
                ? ['id' => $this->folder->id, 'name' => $this->folder->name] : null),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader
                ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_human' => $this->updated_at?->diffForHumans(),
        ];
    }
}
