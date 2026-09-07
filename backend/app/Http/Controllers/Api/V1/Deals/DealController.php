<?php
namespace App\Http\Controllers\Api\V1\Deals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreDealRequest;
use App\Http\Requests\Deals\UpdateDealRequest;
use App\Http\Resources\DealResource;
use App\Http\Resources\PipelineResource;
use App\Models\Deal;
use App\Models\LostReason;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\DealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class DealController extends Controller
{
    private const MAX_UPLOAD_KB = 15360;   // 15 MB, same ceiling as leads/chat media

    public function __construct(private readonly DealService $deals) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'           => 'nullable|string|max:191',
            'pipeline_id' => 'nullable|integer',
            'stage_id'    => 'nullable|integer',
            'status'      => 'nullable|string|in:all,open,won,lost',
            'customer_id' => 'nullable|integer',
            'owner_id'    => 'nullable|string',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated(
            $this->deals->paginate($filters, (int) ($filters['per_page'] ?? 25)),
            DealResource::class
        );
    }

    public function board(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pipeline_id' => 'nullable|integer',
            'owner_id'    => 'nullable|string',
        ]);
        $pipelineId = $data['pipeline_id'] ?? Pipeline::where('is_default', true)->value('id') ?? Pipeline::value('id');
        if (!$pipelineId) return $this->error('No pipeline configured.', 422);
        return $this->success($this->deals->board((int) $pipelineId, $data));
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->deals->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'pipelines'    => PipelineResource::collection(
                Pipeline::where('is_active', true)->with('stages')->orderBy('sort_order')->get()
            ),
            'lost_reasons' => LostReason::where('is_active', true)->orderBy('sort_order')->get(['id','name','code']),
            'statuses'     => Deal::STATUSES,
            'forecast_categories' => Deal::FORECAST_CATEGORIES,
            'next_deal_no' => $this->deals->nextDealNo(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new DealResource($this->deals->find($id)));
    }

    public function store(StoreDealRequest $request): JsonResponse
    {
        try {
            $deal = $this->deals->create($request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new DealResource($deal), 'Deal created', 201);
    }

    public function update(UpdateDealRequest $request, int $id): JsonResponse
    {
        $deal = Deal::findOrFail($id);
        try {
            $deal = $this->deals->update($deal, $request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new DealResource($deal), 'Deal updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Deal::findOrFail($id)->delete();
        return $this->success(null, 'Deal deleted');
    }

    /** Move a deal to another stage (kanban drag / stage dropdown). */
    public function move(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['stage_id' => 'required|integer|exists:pipeline_stages,id']);
        $deal = Deal::findOrFail($id);
        try {
            $deal = $this->deals->moveStage($deal, (int) $data['stage_id']);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new DealResource($deal), 'Deal moved');
    }

    public function markLost(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lost_reason_id' => 'nullable|integer|exists:lost_reasons,id',
            'note'           => 'nullable|string|max:2000',
        ]);
        $deal = Deal::findOrFail($id);
        // markLost() routes through update(), which enforces the target stage's blueprint
        // required_fields — so a Lost stage configured to require lost_reason_id throws here.
        // Every sibling endpoint (store/update/move) already caught this; without it the
        // rejection surfaced as a 500 instead of the 422 the rest of the module returns.
        try {
            $deal = $this->deals->markLost($deal, $data['lost_reason_id'] ?? null, $data['note'] ?? null);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new DealResource($deal), 'Deal marked lost');
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|string|in:note,call,email,meeting',
        ]);
        $deal = Deal::findOrFail($id);
        $entry = $this->deals->addNote($deal, $data['body'], $data['type'] ?? 'note');
        return $this->success([
            'id' => $entry->id, 'type' => $entry->type, 'title' => $entry->title, 'body' => $entry->body,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'occurred_human' => $entry->occurred_at?->diffForHumans(),
        ], 'Note added', 201);
    }

    public function storeAttachment(Request $request, int $id): JsonResponse
    {
        $deal = Deal::findOrFail($id);
        $request->validate([
            'file' => 'required|file|max:'.self::MAX_UPLOAD_KB.'|mimetypes:'.implode(',', [
                'image/jpeg','image/png','image/gif','image/webp',
                'application/pdf','text/plain','text/csv',
                'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
        ]);
        $file = $request->file('file');
        $path = $file->store('deals/'.date('Y/m'), 'local');
        $attachment = $deal->attachments()->create([
            'company_id'  => $deal->company_id,
            'uploaded_by' => auth()->id(),
            'disk' => 'local', 'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        return $this->success([
            'id' => $attachment->id, 'name' => $attachment->name, 'mime' => $attachment->mime,
            'size' => $attachment->size, 'kind' => $attachment->kind, 'url' => $attachment->url,
        ], 'Attachment uploaded', 201);
    }

    public function destroyAttachment(int $id, int $attachmentId): JsonResponse
    {
        $deal = Deal::findOrFail($id);
        $deal->attachments()->findOrFail($attachmentId)->delete();
        return $this->success(null, 'Attachment deleted');
    }

    /**
     * Configure a stage's Blueprint: which deal fields it requires, and which stages it may move
     * to next. Deliberately narrow — no stage create/delete/rename here, just these two rules.
     */
    public function updateStageBlueprint(Request $request, int $pipelineId, int $stageId): JsonResponse
    {
        $stage = PipelineStage::where('pipeline_id', $pipelineId)->findOrFail($stageId);
        $data = $request->validate([
            'required_fields' => ['nullable', 'array'],
            'required_fields.*' => [Rule::in(Deal::BLUEPRINT_FIELDS)],
            'allowed_next_stage_ids' => ['nullable', 'array'],
            'allowed_next_stage_ids.*' => ['integer', Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $pipelineId)],
        ]);
        $stage->update([
            'required_fields' => $data['required_fields'] ?? null,
            'allowed_next_stage_ids' => $data['allowed_next_stage_ids'] ?? null,
        ]);
        return $this->success([
            'id' => $stage->id,
            'required_fields' => $stage->required_fields,
            'allowed_next_stage_ids' => $stage->allowed_next_stage_ids,
        ], 'Blueprint updated');
    }
}
