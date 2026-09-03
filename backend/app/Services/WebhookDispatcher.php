<?php
namespace App\Services;

use App\Models\Workflow;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Delivers a real, HMAC-signed HTTP POST for the workflow `webhook` action. The signing key is
 * derived from APP_KEY + the workflow id rather than stored, so there's nothing extra to leak —
 * WorkflowResource returns action config verbatim to anyone with workflows.view.
 */
class WebhookDispatcher
{
    public function send(Workflow $workflow, string $event, array $payload, string $url): string
    {
        if (!$this->isUrlAllowed($url)) {
            throw new RuntimeException('Webhook URL is not allowed (must be http/https and not point at a local/private address).');
        }

        $body = json_encode(array_merge($payload, [
            'event' => $event,
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'timestamp' => now()->toIso8601String(),
        ]), JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $body, $this->signingSecret($workflow));

        $response = Http::withOptions(['curl' => [CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]])
            ->withoutRedirecting()
            ->withBody($body, 'application/json')
            ->withHeaders(['X-Krama-Signature' => 'sha256='.$signature, 'X-Krama-Event' => $event])
            ->timeout(5)
            ->post($url);

        if (!$response->successful()) {
            throw new RuntimeException('Webhook responded '.$response->status());
        }
        return 'Webhook '.$response->status().' to '.$url;
    }

    /** Deterministic per-workflow key — nothing stored, so this can be surfaced read-only later without a migration. */
    private function signingSecret(Workflow $workflow): string
    {
        return hash_hmac('sha256', 'workflow:'.$workflow->id, (string) config('app.key'));
    }

    private function isUrlAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }
        // Local dev needs to reach host-machine listeners (Mailpit-style), same carve-out used elsewhere.
        if (app()->environment('local')) return true;

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) return false;

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return true;
    }
}
