<?php
namespace App\Services;

use App\Models\SmsProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Click-to-call bridging via Twilio Voice. Reuses the tenant's Twilio SMS-provider credentials
 *  (one Twilio account does SMS + voice) plus a voice-capable "from" number. */
class TelephonyService
{
    public function provider(int $companyId): ?SmsProvider
    {
        return SmsProvider::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('provider', 'twilio')->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('id')->first();
    }

    public function voiceFrom(?SmsProvider $p): ?string
    {
        if (!$p) return null;
        $c = $p->config ?? [];
        return ($c['voice_from'] ?? null) ?: ($p->sender_id ?: null);
    }

    public function isConfigured(int $companyId): bool
    {
        $p = $this->provider($companyId);
        $c = $p?->config ?? [];
        return $p && filled($c['account_sid'] ?? null) && filled($c['auth_token'] ?? null) && filled($this->voiceFrom($p));
    }

    /** Ring the agent; when they answer, Twilio fetches $twimlUrl which dials the customer. Returns the call SID. */
    public function dial(SmsProvider $p, string $agentPhone, string $twimlUrl): string
    {
        $c = $p->config;
        $resp = Http::asForm()->withBasicAuth($c['account_sid'], $c['auth_token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$c['account_sid']}/Calls.json", [
                'To' => $agentPhone, 'From' => $this->voiceFrom($p), 'Url' => $twimlUrl,
            ]);
        if ($resp->failed()) {
            throw new RuntimeException('Twilio: ' . substr((string) ($resp->json('message') ?? $resp->body()), 0, 200));
        }
        return (string) ($resp->json('sid') ?? '');
    }
}
