<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreWebhookEndpointRequest;
use App\Models\WebhookEndpoint;
use App\Services\WebhookEndpointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointController extends Controller
{
    public function __construct(private readonly WebhookEndpointService $endpoints) {}

    public function index(): JsonResponse
    {
        return $this->success(
            WebhookEndpoint::latest('id')->get()->map(fn ($e) => $this->endpoints->present($e))->all()
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        return $this->success($this->endpoints->create($request->validated()), 'Webhook endpoint created', 201);
    }

    public function show(int $id): JsonResponse
    {
        $e = WebhookEndpoint::with('events')->findOrFail($id);
        return $this->success($this->endpoints->present($e) + [
            'events' => $e->events->take(20)->map(fn ($ev) => [
                'id' => $ev->id, 'status' => $ev->status, 'event_id' => $ev->event_id,
                'error' => $ev->error, 'result' => $ev->result,
                'created_at' => optional($ev->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $e = WebhookEndpoint::findOrFail($id);
        $e->fill(array_filter($data, fn ($v) => $v !== null))->save();
        return $this->success($this->endpoints->present($e), 'Updated');
    }

    public function destroy(int $id): JsonResponse
    {
        WebhookEndpoint::findOrFail($id)->delete();
        return $this->success(null, 'Deleted');
    }
}
