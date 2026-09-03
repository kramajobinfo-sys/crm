<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Shared polymorphic attachment. Leads now; deals, tickets and quotes later. */
class Attachment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','attachable_type','attachable_id','uploaded_by',
        'disk','path','name','mime','size',
    ];
    protected function casts(): array { return ['size' => 'integer']; }

    public function attachable(): MorphTo { return $this->morphTo(); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    /** Resolve through the configured disk rather than assuming 'public'. */
    public function getUrlAttribute(): ?string
    {
        return $this->path
            ? URL::temporarySignedRoute('attachments.show', now()->addHour(), ['type' => 'att', 'id' => $this->id], false)
            : null;
    }

    public function getKindAttribute(): string
    {
        $mime = (string) $this->mime;
        foreach (['image', 'video', 'audio'] as $kind) {
            if (str_starts_with($mime, $kind.'/')) return $kind;
        }
        return 'file';
    }
}
