<?php
namespace App\Services;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class WebhookIngestService
{
    public function __construct(private readonly LeadService $leads) {}

    /** Constant-time HMAC-SHA256 verification of the raw body against the endpoint secret. */
    public function verify(WebhookEndpoint $endpoint, string $rawBody, ?string $signature): bool
    {
        if (!$signature) return false;
        try { $secret = Crypt::decryptString($endpoint->secret); }
        catch (\Throwable) { return false; }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Record + process a verified event, idempotently.
     * @return array{0:int,1:array} [httpStatus, responseBody]
     */
    public function ingest(WebhookEndpoint $endpoint, array $payload, string $eventId): array
    {
        $existing = WebhookEvent::withoutGlobalScopes()
            ->where('webhook_endpoint_id', $endpoint->id)->where('event_id', $eventId)->first();
        if ($existing) {
            return [200, ['ok' => true, 'idempotent' => true, 'event_id' => $eventId, 'status' => $existing->status]];
        }

        $event = WebhookEvent::create([
            'company_id' => $endpoint->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => $eventId,
            'status' => 'received',
            'payload' => $payload,
        ]);

        // Company scoping is auth-based, so run the handler as the endpoint's company.
        $this->actAsCompany($endpoint);

        try {
            $result = match ($endpoint->type) {
                'web_to_lead' => $this->handleWebToLead($payload),
                'email_status' => $this->handleEmailStatus($payload, $endpoint->company_id),
                default => throw new \RuntimeException('Unsupported webhook type: ' . $endpoint->type),
            };
            $event->forceFill(['status' => 'processed', 'result' => $result])->save();
            $endpoint->forceFill(['last_received_at' => now()])->save();
            return [200, ['ok' => true, 'event_id' => $eventId] + $result];
        } catch (InvalidArgumentException $e) {
            $event->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            return [422, ['ok' => false, 'error' => $e->getMessage(), 'event_id' => $eventId]];
        } catch (\Throwable $e) {
            $event->forceFill(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)])->save();
            return [500, ['ok' => false, 'error' => 'Processing failed', 'event_id' => $eventId]];
        }
    }

    private function actAsCompany(WebhookEndpoint $endpoint): void
    {
        $user = ($endpoint->created_by ? User::withoutGlobalScopes()->find($endpoint->created_by) : null)
            ?? User::withoutGlobalScopes()->where('company_id', $endpoint->company_id)
                ->where('is_active', true)->orderBy('id')->first();
        if ($user) auth()->setUser($user);
    }

    /** @return array{lead_id:int, lead_no:string} */
    private function handleWebToLead(array $p): array
    {
        $name = trim((string) ($p['name'] ?? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))));
        if ($name === '') throw new InvalidArgumentException('A name (or first_name/last_name) is required.');

        $lead = $this->leads->create([
            'name' => $name,
            'email' => $p['email'] ?? null,
            'phone' => $p['phone'] ?? null,
            'mobile' => $p['mobile'] ?? null,
            'company_name' => $p['company'] ?? ($p['company_name'] ?? null),
            'website' => $p['website'] ?? null,
            'notes' => $p['message'] ?? ($p['notes'] ?? null),
            'estimated_value' => is_numeric($p['value'] ?? null) ? (float) $p['value'] : 0,
        ]);
        return ['lead_id' => $lead->id, 'lead_no' => $lead->lead_no];
    }

    /**
     * Email delivery-status callback (ESP bounce/complaint). Hard bounces and complaints
     * add the address to the marketing suppression list (honored by campaign delivery).
     * Accepts a single event, a {events:[...]} batch, or a top-level array of events, with
     * provider-agnostic field names.
     * @return array{processed:int, suppressed:int}
     */
    private function handleEmailStatus(array $payload, int $companyId): array
    {
        $suppressed = 0; $processed = 0;
        foreach ($this->normalizeEvents($payload) as $ev) {
            if (!is_array($ev)) continue;
            $processed++;
            $email = strtolower(trim((string) ($ev['email'] ?? $ev['recipient'] ?? $ev['Recipient'] ?? $ev['to'] ?? '')));
            $type  = strtolower((string) ($ev['event'] ?? $ev['type'] ?? $ev['eventType'] ?? $ev['record_type'] ?? ''));
            $extra = strtolower((string) ($ev['bounce_type'] ?? $ev['subtype'] ?? $ev['severity'] ?? $ev['reason'] ?? ''));
            if ($email === '') continue;
            if ($this->isSoft($type . ' ' . $extra)) continue;      // transient — don't suppress
            $reason = $this->suppressReason($type);
            if (!$reason) continue;

            EmailSuppression::updateOrCreate(
                ['company_id' => $companyId, 'email' => $email],
                ['source' => $reason, 'campaign_id' => null],
            );
            $suppressed++;
        }
        return ['processed' => $processed, 'suppressed' => $suppressed];
    }

    /** @return array<int, mixed> */
    private function normalizeEvents(array $p): array
    {
        if (isset($p['events']) && is_array($p['events'])) return array_values($p['events']);
        if (array_is_list($p)) return $p;   // a top-level JSON array of events
        return [$p];                        // a single event object
    }

    private function isSoft(string $s): bool
    {
        foreach (['soft', 'transient', 'deferred', 'delayed', 'temporary'] as $k) {
            if (str_contains($s, $k)) return true;
        }
        return false;
    }

    /** Map an ESP event name to a suppression reason, or null if it shouldn't suppress. */
    private function suppressReason(string $type): ?string
    {
        if (str_contains($type, 'complaint') || str_contains($type, 'spam')) return 'complaint';
        if (str_contains($type, 'unsubscribe')) return 'unsubscribe';
        foreach (['bounce', 'blocked', 'dropped', 'failed', 'rejected'] as $k) {
            if (str_contains($type, $k)) return 'bounce';
        }
        return null;
    }
}
