<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['open', 'won', 'lost'];
    public const CONTACT_ROLES = ['decision_maker', 'champion', 'influencer', 'evaluator', 'billing', 'other'];

    /** Fields a pipeline stage's Blueprint may require before a deal can enter it. */
    public const BLUEPRINT_FIELDS = ['amount', 'customer_id', 'owner_id', 'expected_close_date', 'probability', 'lost_reason_id'];

    protected $fillable = [
        'company_id','deal_no','title','pipeline_id','stage_id','customer_id','lead_id',
        'owner_id','branch_id','amount','currency','probability','status','expected_close_date',
        'won_at','lost_at','lost_reason_id','source','notes',
    ];
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo   { return $this->belongsTo(Pipeline::class); }
    public function stage(): BelongsTo      { return $this->belongsTo(PipelineStage::class, 'stage_id'); }
    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo        { return $this->belongsTo(Lead::class); }
    public function owner(): BelongsTo       { return $this->belongsTo(User::class, 'owner_id'); }
    public function branch(): BelongsTo      { return $this->belongsTo(Branch::class); }
    public function lostReason(): BelongsTo  { return $this->belongsTo(LostReason::class, 'lost_reason_id'); }
    public function products(): HasMany      { return $this->hasMany(DealProduct::class)->orderBy('sort_order'); }
    public function project(): HasOne        { return $this->hasOne(Project::class); }
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'deal_contact')
            ->withPivot(['company_id', 'role', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('contacts.name');
    }

    public function addresses(): MorphMany   { return $this->morphMany(Address::class, 'addressable'); }
    public function timeline(): MorphMany    { return $this->morphMany(TimelineActivity::class, 'subject'); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }

    public function isOpen(): bool { return $this->status === 'open'; }
    public function isWon(): bool  { return $this->status === 'won'; }
    public function isLost(): bool { return $this->status === 'lost'; }

    /** Amount weighted by win probability — the honest forecast figure. */
    public function getWeightedAmountAttribute(): float
    {
        return round(((float) $this->amount) * ((int) $this->probability) / 100, 2);
    }

    public function scopeOpen(Builder $q): Builder   { return $q->where('status', 'open'); }
    public function scopeWon(Builder $q): Builder    { return $q->where('status', 'won'); }
    public function scopeLost(Builder $q): Builder   { return $q->where('status', 'lost'); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('title', 'like', $like)
            ->orWhere('deal_no', 'like', $like));
    }
}
