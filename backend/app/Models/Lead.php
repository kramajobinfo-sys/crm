<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const RATINGS = ['hot', 'warm', 'cold'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const CAMPAIGN_MEMBER_STATUSES = ['member', 'contacted', 'responded'];

    protected $fillable = [
        'company_id','lead_no','name','company_name','account_id','title','email','phone','mobile','website',
        'source_id','campaign_id','status_id','lost_reason_id','score','rating','priority','owner_id','branch_id','territory',
        'estimated_value','currency','expected_close_date','last_contacted_at','follow_up_at','next_action',
        'converted_to_customer_id','converted_at','notes','custom_fields',
    ];
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'score' => 'integer',
            'estimated_value' => 'decimal:2',
            'expected_close_date' => 'date',
            'last_contacted_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo   { return $this->belongsTo(LeadSource::class, 'source_id'); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    /** Marketing-list memberships: many campaigns, each with a member status. Distinct from the
     *  single source campaign in campaign_id above. */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_lead')
            ->withPivot(['status', 'added_at'])->withTimestamps();
    }
    public function status(): BelongsTo   { return $this->belongsTo(LeadStatus::class, 'status_id'); }
    public function lostReason(): BelongsTo { return $this->belongsTo(LostReason::class, 'lost_reason_id'); }
    public function owner(): BelongsTo    { return $this->belongsTo(User::class, 'owner_id'); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class, 'converted_to_customer_id'); }
    public function account(): BelongsTo  { return $this->belongsTo(Customer::class, 'account_id'); }
    public function deals(): HasMany      { return $this->hasMany(Deal::class); }
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_products')
            ->withPivot(['quantity', 'note'])->withTimestamps();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(LeadConsent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function consentFor(string $channel): ?LeadConsent
    {
        return $this->consents->firstWhere('channel', $channel);
    }

    public function canReceive(string $channel): bool
    {
        $latest = $this->consentFor($channel);
        if ($latest) return $latest->status === 'granted';
        return !in_array($channel, LeadConsent::OPT_IN_CHANNELS, true);
    }

    public function addresses(): MorphMany   { return $this->morphMany(Address::class, 'addressable'); }
    public function timeline(): MorphMany    { return $this->morphMany(TimelineActivity::class, 'subject'); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }

    public function isConverted(): bool { return $this->converted_to_customer_id !== null; }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('converted_to_customer_id');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('lead_no', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }
}
