<?php
namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreWorkflowRequest;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Services\ApprovalService;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchase,
        private readonly ApprovalService $approvals,
    ) {}

    /** Approvals currently awaiting the signed-in user. */
    public function mine(): JsonResponse
    {
        return $this->success($this->approvals->pendingFor(auth()->id())->map(function (ApprovalRequest $r) {
            $doc = $r->approvable;
            return [
                'id' => $r->id,
                'document_type' => class_basename($r->approvable_type),
                'document_no' => $doc->pr_no ?? $doc->po_no ?? $doc->quote_no ?? ('#'.$r->approvable_id),
                'amount' => (float) ($doc->grand_total ?? $doc->estimated_total ?? 0),
                'step' => $r->current_step + 1,
                'total_steps' => count($r->workflow->approver_ids ?? []),
                'workflow' => $r->workflow?->name,
                'requester' => $r->requester?->name,
                'requested_at' => $r->created_at?->toIso8601String(),
            ];
        }));
    }

    public function act(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:2000',
        ]);
        $approval = ApprovalRequest::findOrFail($id);
        try {
            $approval = $this->purchase->actOnApproval($approval, $data['action'], $data['comment'] ?? null);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success([
            'id' => $approval->id, 'status' => $approval->status, 'current_step' => $approval->current_step,
        ], 'Decision recorded');
    }

    // ---- workflow configuration -----------------------------------------

    public function workflows(): JsonResponse
    {
        return $this->success($this->purchase->workflows()->map(fn (ApprovalWorkflow $w) => [
            'id' => $w->id, 'name' => $w->name, 'document_type' => $w->document_type,
            'min_amount' => (float) $w->min_amount, 'approver_ids' => $w->approver_ids,
            'steps' => $w->steps(), 'is_active' => (bool) $w->is_active,
        ]));
    }

    public function storeWorkflow(StoreWorkflowRequest $request): JsonResponse
    {
        $w = $this->purchase->createWorkflow($request->validated());
        return $this->success(['id' => $w->id, 'name' => $w->name], 'Workflow created', 201);
    }

    public function updateWorkflow(StoreWorkflowRequest $request, int $id): JsonResponse
    {
        $w = ApprovalWorkflow::findOrFail($id);
        $this->purchase->updateWorkflow($w, $request->validated());
        return $this->success(['id' => $w->id, 'name' => $w->name], 'Workflow updated');
    }

    public function destroyWorkflow(int $id): JsonResponse
    {
        ApprovalWorkflow::findOrFail($id)->delete();
        return $this->success(null, 'Workflow deleted');
    }
}
