<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbArticle extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES     = ['draft', 'published'];
    public const VISIBILITIES = ['internal', 'public'];

    protected $fillable = [
        'company_id','category_id','title','slug','body','excerpt',
        'status','visibility','author_id','published_at',
    ];
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'view_count' => 'integer'];
    }

    public function category(): BelongsTo { return $this->belongsTo(KbCategory::class, 'category_id'); }
    public function author(): BelongsTo   { return $this->belongsTo(User::class, 'author_id'); }

    /** The portal double-gate: an article is customer-visible only when BOTH hold. */
    public function scopePortalVisible(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('visibility', 'public');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('title', 'like', $like)
            ->orWhere('excerpt', 'like', $like)
            ->orWhere('body', 'like', $like));
    }
}
