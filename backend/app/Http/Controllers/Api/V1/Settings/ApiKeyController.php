<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiKeyRequest;
use App\Http\Requests\Settings\UpdateApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $apiKeys) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->apiKeys->paginate((int) ($request->query('per_page') ?? 25)), ApiKeyResource::class);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new ApiKeyResource($this->apiKeys->find($id)));
    }

    /** The only response that ever contains the plaintext key. */
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        try {
            $result = $this->apiKeys->create($request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'api_key' => new ApiKeyResource($result['api_key']),
            'plain_key' => $result['plain_key'],
        ], 'API key created — copy it now, it will not be shown again', 201);
    }

    public function update(UpdateApiKeyRequest $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::findOrFail($id);
        try {
            $updated = $this->apiKeys->update($apiKey, $request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new ApiKeyResource($updated), 'API key updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->apiKeys->delete(ApiKey::findOrFail($id));
        return $this->success(null, 'API key deleted');
    }
}
