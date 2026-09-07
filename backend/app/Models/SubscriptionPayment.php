<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use BelongsToCompany;

    public const METHODS = ['cod', 'khqr', 'aba_khqr'];

    protected $fillable = [
        'company_id', 'subscription_id', 'plan_id', 'method', 'provider', 'currency', 'amount',
        'status', 'provider_ref', 'md5', 'qr_payload', 'deeplink', 'receipt_path', 'note',
        'paid_at', 'expires_at', 'meta',
    ];

    protected $hidden = ['md5'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === 'pending';
    }
}
