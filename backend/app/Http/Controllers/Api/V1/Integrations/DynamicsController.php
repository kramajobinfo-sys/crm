<?php
namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Resources\BcConnectionResource;
use App\Http\Resources\BcEntityMappingResource;
use App\Http\Resources\BcSyncRunResource;
use App\Jobs\RunBcSync;
use App\Models\BcConnection;
use App\Models\BcEntityMapping;
use App\Models\BcSyncRun;
use App\Services\Dynamics\SyncEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicsController extends Controller
{
    public function __construct(private readonly SyncEngine $engine) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'connections' => BcConnectionResource::collection(
                BcConnection::with('mappings')->orderBy('name')->get()
            ),
            'supported_bc_entities' => SyncEngine::SUPPORTED_BC_ENTITIES,
            // Which CRM entities actually have a syncer today — the UI must not
            // offer a mapping that would fail at run time.
            'implemented_crm_entities' => $this->engine->implementedEntities(),
            'directions' => BcEntityMapping::DIRECTIONS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:128',
            'environment'   => 'nullable|string|max:64',
            'tenant_id'     => 'nullable|string|max:191',
            'client_id'     => 'nullable|string|max:191',
            'client_secret' => 'nullable|string|max:512',
            'base_url'      => 'nullable|url|max:255',
            'bc_company_id' => 'nullable|string|max:64',
        ]);
        $connection = BcConnection::create($data + ['status' => 'unconfigured']);
        // refresh() so DB-level defaults (is_active, api_version) are in the response
        return $this->success(new BcConnectionResource($connection->refresh()), 'Connection created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $connection = BcConnection::findOrFail($id);
        $data = $request->validate([
            'name'            => 'sometimes|string|max:128',
            'environment'     => 'nullable|string|max:64',
            'tenant_id'       => 'nullable|string|max:191',
            'client_id'       => 'nullable|string|max:191',
            'client_secret'   => 'nullable|string|max:512',
            'base_url'        => 'nullable|url|max:255',
            'bc_company_id'   => 'nullable|string|max:64',
            'bc_company_name' => 'nullable|string|max:191',
            'is_active'       => 'nullable|boolean',
        ]);
        // An empty secret means "leave it alone", never "erase it".
        if (array_key_exists('client_secret', $data) && $data['client_secret'] === null) {
            unset($data['client_secret']);
        }
        $connection->update($data);
        return $this->success(new BcConnectionResource($connection->fresh()), 'Connection updated');
    }

    public function destroy(int $id): JsonResponse
    {
        BcConnection::findOrFail($id)->delete();
        return $this->success(null, 'Connection deleted');
    }

    /** Live probe against Entra ID + BC. Returns 200 with ok:false on failure — a failed
     *  probe is a valid result to render, not a server error. */
    public function test(int $id): JsonResponse
    {
        $connection = BcConnection::findOrFail($id);
        return $this->success($this->engine->testConnection($connection));
    }

    public function storeMapping(Request $request, int $id): JsonResponse
    {
        $connection = BcConnection::findOrFail($id);
        $data = $request->validate([
            'crm_entity'       => 'required|string|max:64',
            'bc_entity'        => 'required|string|max:64|in:'.implode(',', SyncEngine::SUPPORTED_BC_ENTITIES),
            'direction'        => 'required|string|in:'.implode(',', BcEntityMapping::DIRECTIONS),
            'is_enabled'       => 'nullable|boolean',
            'field_map'        => 'nullable|array',
            'filter'           => 'nullable|array',
            'interval_minutes' => 'nullable|integer|min:5|max:10080',
        ]);
        $mapping = $connection->mappings()->create($data + ['company_id' => $connection->company_id]);
        return $this->success(new BcEntityMappingResource($mapping), 'Mapping created', 201);
    }

    public function updateMapping(Request $request, int $id, int $mappingId): JsonResponse
    {
        $mapping = BcEntityMapping::where('connection_id', $id)->findOrFail($mappingId);
        $mapping->update($request->validate([
            'direction'        => 'sometimes|string|in:'.implode(',', BcEntityMapping::DIRECTIONS),
            'is_enabled'       => 'nullable|boolean',
            'field_map'        => 'nullable|array',
            'filter'           => 'nullable|array',
            'interval_minutes' => 'nullable|integer|min:5|max:10080',
        ]));
        return $this->success(new BcEntityMappingResource($mapping->fresh()), 'Mapping updated');
    }

    public function destroyMapping(int $id, int $mappingId): JsonResponse
    {
        BcEntityMapping::where('connection_id', $id)->findOrFail($mappingId)->delete();
        return $this->success(null, 'Mapping deleted');
    }

    /** Queue a sync. Refuses up front when no syncer exists, rather than
     *  dispatching a job that is guaranteed to fail. */
    public function sync(int $id, int $mappingId): JsonResponse
    {
        $mapping = BcEntityMapping::where('connection_id', $id)->findOrFail($mappingId);

        if (!$this->engine->syncerFor($mapping->crm_entity)) {
            return $this->error(
                "No syncer is implemented for '{$mapping->crm_entity}' yet — it ships with that module.",
                422
            );
        }
        RunBcSync::dispatch($mapping->id, 'manual');
        return $this->success(null, 'Sync queued', 202);
    }

    public function runs(Request $request, int $id): JsonResponse
    {
        $runs = BcSyncRun::where('connection_id', $id)
            ->with(['mapping:id,crm_entity,bc_entity'])
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));
        return $this->paginated($runs, BcSyncRunResource::class);
    }

    public function run(int $id, int $runId): JsonResponse
    {
        $run = BcSyncRun::where('connection_id', $id)->with(['mapping', 'issues'])->findOrFail($runId);
        return $this->success(new BcSyncRunResource($run));
    }
}
