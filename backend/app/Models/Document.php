<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A library document. Files live on the PRIVATE disk; there is deliberately NO url accessor —
 * the only access path is the authenticated streaming endpoint (see DocumentController::download).
 * No SoftDeletes: deleting removes the row and best-effort deletes the file. See docs/DOCUMENTS_SCOPE.md.
 */
class Document extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','folder_id','name','description','disk','path','mime','size','uploaded_by',
    ];
    protected function casts(): array { return ['size' => 'integer']; }

    public function folder(): BelongsTo   { return $this->belongsTo(DocumentFolder::class, 'folder_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getKindAttribute(): string
    {
        $mime = (string) $this->mime;
        foreach (['image', 'video', 'audio'] as $kind) {
            if (str_starts_with($mime, $kind.'/')) return $kind;
        }
        if ($mime === 'application/pdf') return 'pdf';
        return 'file';
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('description', 'like', $like));
    }
}
