<?php
namespace App\Services;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Notifications\CrmNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generic multi-step approval engine. A workflow is an ordered list of approver user ids;
 * an approval_request walks that list one step at a time until every approver has approved
 * (→ approved) or any approver rejects (→ rejected).
 */
class ApprovalService
{
    /**
     * Open an approval for a document if a matching active workflow exists.
     * Returns the pending request, or null when no workflow applies (caller auto-approves).
     */
    public function initiate(Model $document, string $documentType, float $amount): ?ApprovalRequest
    {
        $workflow = ApprovalWorkflow::where('document_type', $documentType)
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->orderByDesc('min_amount')
            ->get()
            ->first(fn (ApprovalWorkflow $w) => $w->steps() > 0);

        if (!$workflow) return null;

        $request = ApprovalRequest::create([
            'company_id' => $document->company_id,
            'workflow_id' => $workflow->id,
            'approvable_type' => $document::class,
            'approvable_id' => $document->getKey(),
            'status' => 'pending',
            'current_step' => 0,
            'requested_by' => auth()->id(),
        ]);

        $this->notifyPendingApprover($request, $document);
        return $request;
    }

    /**
     * Record the current approver's decision. Only the user whose step is current may act.
     * Approving the last step resolves to approved; any rejection resolves to rejected.
     */
    public function act(ApprovalRequest $request, string $action, ?string $comment = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException('This approval has already been '.$request->status.'.');
        }
        $request->loadMissing('workflow');
        $expected = $request->currentApproverId();
        if ($expected !== null && $expected !== auth()->id() && !auth()->user()->isPlatformAdmin()) {
            throw new RuntimeException('It is not your turn to approve this request.');
        }

        $request = DB::transaction(function () use ($request, $action, $comment) {
            ApprovalAction::create([
                'company_id' => $request->company_id,
                'approval_request_id' => $request->id,
                'step' => $request->current_step,
                'approver_id' => auth()->id(),
                'action' => $action,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            if ($action === 'reject') {
                $request->forceFill(['status' => 'rejected'])->save();
                $this->syncQuotation($request);
                return $request;
            }

            // approve: advance, or resolve when the last step is done
            $isLast = $request->current_step >= $request->workflow->steps() - 1;
            if ($isLast) {
                $request->forceFill(['status' => 'approved'])->save();
                $this->syncQuotation($request);
            } else {
                $request->forceFill(['current_step' => $request->current_step + 1])->save();
            }
            return $request;
        });

        // Notify after commit: requester on a final decision, the next approver on an advance.
        if ($request->status === 'approved' || $request->status === 'rejected') {
            $this->notifyRequester($request, $request->status);
        } else {
            $this->notifyPendingApprover($request);
        }
        return $request;
    }

    /** Alert the approver whose step is now current that a document is waiting on them. */
    private function notifyPendingApprover(ApprovalRequest $request, ?Model $document = null): void
    {
        $doc = $document ?? $request->approvable()->first();
        app(CrmNotifier::class)->toUser($request->currentApproverId(), new CrmNotification(
            'approval.requested',
            'Approval needed: '.$this->docLabel($doc),
            $this->docUrl($doc),
            ['approval_request_id' => $request->id],
        ));
    }

    /** Tell whoever submitted the document how their approval resolved. */
    private function notifyRequester(ApprovalRequest $request, string $decision): void
    {
        $doc = $request->approvable()->first();
        app(CrmNotifier::class)->toUser($request->requested_by, new CrmNotification(
            "approval.{$decision}",
            'Approval '.$decision.': '.$this->docLabel($doc),
            $this->docUrl($doc),
            ['approval_request_id' => $request->id],
        ));
    }

    private function docLabel(?Model $doc): string
    {
        if ($doc && method_exists($doc, 'documentNumberColumn')) {
            return (string) ($doc->{$doc->documentNumberColumn()} ?? class_basename($doc));
        }
        return $doc ? class_basename($doc) : 'document';
    }

    private function docUrl(?Model $doc): string
    {
        return $doc instanceof \App\Models\Quotation ? '/app/sales' : '/app/purchase';
    }

    /**
     * When a quotation's approval resolves, return it to draft (now sendable) or leave it revisable
     * on rejection, and log the outcome. PO/PR resolution stays in PurchaseService::applyApproval;
     * this only handles the Quotation document type the sales flow added.
     */
    private function syncQuotation(ApprovalRequest $request): void
    {
        if (!in_array($request->status, ['approved', 'rejected'], true)) return;
        $doc = $request->approvable()->first();
        if (!($doc instanceof \App\Models\Quotation)) return;
        $doc->forceFill(['status' => 'draft'])->save();
        \App\Models\TimelineActivity::record($doc, 'system',
            $request->status === 'approved' ? 'Quotation approved' : 'Quotation approval rejected — revise and resubmit');
    }

    /** Approvals currently waiting on a given user. */
    public function pendingFor(int $userId): Collection
    {
        return ApprovalRequest::where('status', 'pending')
            ->with(['workflow:id,name,approver_ids,document_type', 'approvable', 'requester:id,name'])
            ->get()
            ->filter(fn (ApprovalRequest $r) => $r->currentApproverId() === $userId)
            ->values();
    }
}
