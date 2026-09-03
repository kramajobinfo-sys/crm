<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared history feed rendered on any record's detail page.
 * Distinct from audit_logs: that is a tamper record of field changes, this is
 * the human-facing narrative (notes, calls, emails, status changes).
 */
class TimelineActivity extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['note','call','email','meeting','status_change','system'];

    protected $fillable = [
        'company_id','subject_type','subject_id','user_id','type','title','body','meta','occurred_at',
    ];
    protected function casts(): array { return ['meta' => 'array', 'occurred_at' => 'datetime']; }

    public function subject(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }

    /** Convenience recorder so services do not repeat the boilerplate. */
    public static function record(
        Model $subject,
        string $type,
        string $title,
        ?string $body = null,
        array $meta = [],
    ): self {
        return static::create([
            'company_id'   => $subject->company_id,
            'subject_type' => $subject::class,
            'subject_id'   => $subject->getKey(),
            'user_id'      => auth()->id(),
            'type'         => $type,
            'title'        => $title,
            'body'         => $body,
            'meta'         => $meta ?: null,
            'occurred_at'  => now(),
        ]);
    }
}
