<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'plan_id', 'interval', 'currency', 'amount', 'status',
        'current_period_start', 'current_period_end', 'started_at', 'canceled_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'started_at' => 'datetime',
            'canceled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (!$this->current_period_end || $this->current_period_end->endOfDay()->isFuture());
    }
}
