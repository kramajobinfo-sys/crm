<?php
namespace App\Services;

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
}
