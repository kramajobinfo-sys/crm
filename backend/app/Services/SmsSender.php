<?php
namespace App\Services;

use App\Models\SmsProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Provider-agnostic outbound SMS/WhatsApp send. Credentials live in the provider's encrypted config. */
class SmsSender
{
    public function isConfigured(SmsProvider $provider): bool
    {
        $c = $provider->config ?? [];
        return match ($provider->provider) {
            'twilio'  => filled($c['account_sid'] ?? null) && filled($c['auth_token'] ?? null),
            'generic' => filled($c['url'] ?? null),
            default   => false,
        };
    }

    /** Send one message; throws RuntimeException on failure. */
    public function send(SmsProvider $provider, string $to, string $body): void
    {
        if (trim($to) === '') throw new RuntimeException('No recipient number.');
        if (!$this->isConfigured($provider)) {
            throw new RuntimeException('SMS provider "'.$provider->name.'" is not configured.');
        }
        match ($provider->provider) {
            'twilio'  => $this->sendTwilio($provider, $to, $body),
            'generic' => $this->sendGeneric($provider, $to, $body),
            default   => throw new RuntimeException('Unsupported SMS provider: '.$provider->provider),
        };
    }

    private function sendTwilio(SmsProvider $p, string $to, string $body): void
    {
        $c = $p->config;
        $isWa = ($c['channel'] ?? 'sms') === 'whatsapp';
        $from = $p->sender_id ?: ($c['from'] ?? '');
        if ($isWa) { $to = 'whatsapp:'.$to; $from = 'whatsapp:'.$from; }

        $resp = Http::asForm()->withBasicAuth($c['account_sid'], $c['auth_token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$c['account_sid']}/Messages.json", [
                'To' => $to, 'From' => $from, 'Body' => $body,
            ]);
        if ($resp->failed()) {
            throw new RuntimeException('Twilio: '.substr((string) ($resp->json('message') ?? $resp->body()), 0, 200));
        }
    }

    /** Configurable HTTP gateway: POST to config.url with field-name mapping. */
    private function sendGeneric(SmsProvider $p, string $to, string $body): void
    {
        $c = $p->config;
        $payload = ($c['payload'] ?? []) + [
            ($c['to_field'] ?? 'to')     => $to,
            ($c['body_field'] ?? 'message') => $body,
            ($c['from_field'] ?? 'from') => $p->sender_id ?: ($c['from'] ?? ''),
        ];
        $resp = Http::withHeaders($c['headers'] ?? [])->post($c['url'], $payload);
        if ($resp->failed()) throw new RuntimeException('SMS gateway returned HTTP '.$resp->status());
    }
}
