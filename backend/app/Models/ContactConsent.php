<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactConsent extends Model
{
    use HasFactory, BelongsToCompany;

    /** Channels a contact can be reached on. `marketing` requires explicit opt-in. */
    public const CHANNELS = ['email', 'sms', 'phone', 'whatsapp', 'marketing'];

    /** Channels that require an explicit grant before we may contact (opt-in). */
    public const OPT_IN_CHANNELS = ['marketing'];

    protected $fillable = [
        'company_id', 'contact_id', 'channel', 'status', 'source', 'note', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Contact ids in a company whose LATEST consent for any of $channels is 'withdrawn'.
     * @return array<int>
     */
    public static function withdrawnContactIds(int $companyId, array $channels): array
    {
        $rows = static::query()
            ->where('company_id', $companyId)
            ->whereIn('channel', $channels)
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->get(['contact_id', 'channel', 'status']);

        $seen = []; $withdrawn = [];        // latest row per contact+channel wins
        foreach ($rows as $r) {
            $key = $r->contact_id . '|' . $r->channel;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            if ($r->status === 'withdrawn') $withdrawn[$r->contact_id] = true;
        }
        return array_keys($withdrawn);
    }

    /**
     * Normalized suppression sets (lower-cased emails + digit-only phones) for recipients
     * who opted out of any of $channels. Used to honor opt-outs at send time.
     * @return array{emails: array<string,bool>, phones: array<string,bool>}
     */
    public static function suppression(int $companyId, array $channels): array
    {
        $emails = []; $phones = [];
        $ids = static::withdrawnContactIds($companyId, $channels);
        if ($ids) {
            foreach (Contact::withoutGlobalScopes()->whereIn('id', $ids)->get(['email', 'phone', 'mobile']) as $c) {
                if ($c->email) $emails[strtolower(trim($c->email))] = true;
                foreach ([$c->phone, $c->mobile] as $p) {
                    $digits = $p ? preg_replace('/[^0-9]+/', '', $p) : '';
                    if ($digits) $phones[$digits] = true;
                }
            }
        }
        // Marketing opt-outs captured by email address (public unsubscribe link).
        if (in_array('marketing', $channels, true)) {
            foreach (EmailSuppression::where('company_id', $companyId)->pluck('email') as $e) {
                $emails[strtolower(trim($e))] = true;
            }
        }
        return ['emails' => $emails, 'phones' => $phones];
    }

    /** True if a raw email/phone belongs to someone who opted out of any of $channels. */
    public static function isSuppressed(int $companyId, array $channels, ?string $email, ?string $phone = null): bool
    {
        $s = static::suppression($companyId, $channels);
        if ($email && isset($s['emails'][strtolower(trim($email))])) return true;
        $digits = $phone ? preg_replace('/[^0-9]+/', '', $phone) : '';
        return $digits !== '' && isset($s['phones'][$digits]);
    }
}
