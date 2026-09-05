<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Services\WebhookIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public, no-auth inbound webhook receiver. Requests are verified by HMAC signature. */
class WebhookController extends Controller
{
    public function __construct(private readonly WebhookIngestService $ingest) {}

    public function receive(Request $request, string $slug): JsonResponse
    {
        $endpoint = WebhookEndpoint::withoutGlobalScopes()
            ->where('slug', $slug)->where('is_active', true)->first();
        if (!$endpoint) return response()->json(['ok' => false, 'error' => 'Unknown endpoint'], 404);

        $raw = $request->getContent();
        if (!$this->ingest->verify($endpoint, $raw, $request->header('X-Krama-Signature'))) {
            return response()->json(['ok' => false, 'error' => 'Invalid or missing signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Body must be a JSON object'], 422);
        }

        $eventId = $request->header('X-Krama-Event-Id') ?: hash('sha256', $raw);
        [$status, $body] = $this->ingest->ingest($endpoint, $payload, $eventId);
        return response()->json($body, $status);
    }
}
