<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One browser (by client-generated uid) on a tenant's marketing site. Anonymous until
 * identify() matches it to an existing Lead or Customer — it never creates either.
 */
class WebVisitor extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','visitor_uid','lead_id','customer_id','identified_at',
        'first_landing_url','first_referrer','user_agent','ip_hash',
        'page_view_count','first_seen_at','last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'identified_at' => 'datetime', 'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime', 'page_view_count' => 'integer',
        ];
    }

    public function lead(): BelongsTo      { return $this->belongsTo(Lead::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function pageViews(): HasMany   { return $this->hasMany(WebPageView::class, 'visitor_id'); }

    public function isIdentified(): bool { return $this->lead_id !== null || $this->customer_id !== null; }

    public function scopeIdentified(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNotNull('lead_id')->orWhereNotNull('customer_id'));
    }
}
