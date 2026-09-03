<?php
namespace App\Http\Controllers\Api\V1\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'entity' => ['nullable', 'string', Rule::in(Workflow::ENTITIES)],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->workflows->paginate($f, (int) ($f['per_page'] ?? 25)), WorkflowResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->workflows->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'entities' => Workflow::ENTITIES,
            'trigger_types' => Workflow::TRIGGER_TYPES,
            'events' => Workflow::EVENTS,
            'action_types' => WorkflowAction::TYPES,
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new WorkflowResource($this->workflows->find($id)));
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        return $this->success(new WorkflowResource($this->workflows->create($request->validated())), 'Workflow created', 201);
    }

    public function update(StoreWorkflowRequest $request, int $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        return $this->success(new WorkflowResource($this->workflows->update($workflow, $request->validated())), 'Workflow updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Workflow::findOrFail($id)->delete();
        return $this->success(null, 'Workflow deleted');
    }

    /** Run a workflow now, optionally against a subject record of its entity. */
    public function run(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['subject_id' => 'nullable|integer']);
        $workflow = Workflow::with('actions')->findOrFail($id);
        try {
            $run = $this->workflows->run($workflow, isset($data['subject_id']) ? (string) $data['subject_id'] : null, 'manual');
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success([
            'run' => ['id' => $run->id, 'status' => $run->status, 'actions_run' => $run->actions_run, 'log' => $run->log],
            'workflow' => new WorkflowResource($this->workflows->find($id)),
        ], 'Workflow run');
    }
}
