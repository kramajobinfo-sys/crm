<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-channel consent ledger for a lead. Mirrors ContactConsent so leads carry the same opt-in/
 * opt-out semantics; marketing suppression unions these with contact opt-outs.
 */
class LeadConsent extends Model
{
    use HasFactory, BelongsToCompany;

    public const CHANNELS = ['email', 'sms', 'phone', 'whatsapp', 'marketing'];
    public const OPT_IN_CHANNELS = ['marketing'];

    protected $fillable = [
        'company_id', 'lead_id', 'channel', 'status', 'source', 'note', 'user_id', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Lead ids in a company whose LATEST consent for any of $channels is 'withdrawn'.
     * @return array<int>
     */
    public static function withdrawnLeadIds(int $companyId, array $channels): array
    {
        $rows = static::query()
            ->where('company_id', $companyId)
            ->whereIn('channel', $channels)
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->get(['lead_id', 'channel', 'status']);

        $seen = []; $withdrawn = [];
        foreach ($rows as $r) {
            $key = $r->lead_id.'|'.$r->channel;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            if ($r->status === 'withdrawn') $withdrawn[$r->lead_id] = true;
        }
        return array_keys($withdrawn);
    }
}
