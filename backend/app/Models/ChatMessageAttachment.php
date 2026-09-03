<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ChatMessageAttachment extends Model
{
    use HasFactory;

    // Scoped through its parent message; no company_id column of its own.
    protected $fillable = [
        'message_id','disk','path','thumbnail_path','name','mime','size',
        'width','height','duration_seconds',
    ];
    protected function casts(): array
    {
        return [
            'size' => 'integer', 'width' => 'integer',
            'height' => 'integer', 'duration_seconds' => 'integer',
        ];
    }

    public function message(): BelongsTo { return $this->belongsTo(ChatMessage::class, 'message_id'); }

    /** Resolve through the configured disk rather than assuming 'public'. */
    public function getUrlAttribute(): ?string
    {
        return $this->path
            ? URL::temporarySignedRoute('attachments.show', now()->addHour(), ['type' => 'chat', 'id' => $this->id], false)
            : null;
    }
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? URL::temporarySignedRoute('attachments.show', now()->addHour(), ['type' => 'chat', 'id' => $this->id, 'field' => 'thumb'], false)
            : null;
    }

    /** Coarse bucket the UI renders on: image | video | audio | file. */
    public function getKindAttribute(): string
    {
        $mime = (string) $this->mime;
        foreach (['image', 'video', 'audio'] as $kind) {
            if (str_starts_with($mime, $kind.'/')) return $kind;
        }
        return 'file';
    }
}
