<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const TYPES    = ['company', 'individual'];
    public const STATUSES = ['active', 'on_hold', 'blocked', 'archived'];

    protected $fillable = [
        'company_id','customer_no','type','group_id','owner_id','branch_id','territory','tags','name','legal_name',
        'email','phone','mobile','website','tax_id','currency','price_book_id','credit_limit',
        'payment_terms_days','status','notes','converted_from_lead_id','custom_fields',
    ];
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom_fields' => 'array',
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
        ];
    }

    public function group(): BelongsTo    { return $this->belongsTo(CustomerGroup::class, 'group_id'); }
    public function priceBook(): BelongsTo { return $this->belongsTo(PriceBook::class); }
    public function owner(): BelongsTo    { return $this->belongsTo(User::class, 'owner_id'); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function contacts(): HasMany   { return $this->hasMany(Contact::class); }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }
    public function timeline(): MorphMany  { return $this->morphMany(TimelineActivity::class, 'subject'); }

    public function primaryContact(): HasMany
    {
        return $this->hasMany(Contact::class)->where('is_primary', true);
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status && $status !== 'all' ? $q->where('status', $status) : $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('customer_no', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('tax_id', 'like', $like));
    }

    /** Effective payment terms: the customer's own, else the group's. */
    public function effectivePaymentTerms(): ?int
    {
        return $this->payment_terms_days ?? $this->group?->payment_terms_days;
    }

    /** Effective price book: the customer's own, else the group's (mirrors effectivePaymentTerms). */
    public function effectivePriceBookId(): ?int
    {
        return $this->price_book_id ?? $this->group?->price_book_id;
    }
}
