<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcWebhookSubscription extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','connection_id','resource','subscription_id',
        'notification_url','client_state','expires_at','last_renewed_at',
    ];
    protected $hidden = ['client_state'];
    protected function casts(): array
    {
        return [
            'client_state' => 'encrypted',
            'expires_at' => 'datetime', 'last_renewed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo { return $this->belongsTo(BcConnection::class, 'connection_id'); }

    /** BC subscriptions are short-lived and must be renewed before they lapse. */
    public function needsRenewal(int $withinMinutes = 60): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now()->addMinutes($withinMinutes));
    }
}
