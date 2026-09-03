<?php
namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesDocumentItem;
use App\Models\TaxRate;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly InventoryService $inventory,
    ) {}

    // ---- vendors ---------------------------------------------------------

    public function paginateVendors(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Vendor::query()
            ->search($f['q'] ?? null)
            ->status($f['status'] ?? null)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createVendor(array $data): Vendor
    {
        $data['vendor_no'] ??= $this->nextNumber(Vendor::class, 'vendor_no', 'VEN');
        return Vendor::create($data);
    }

    public function updateVendor(Vendor $vendor, array $data): Vendor
    {
        $vendor->update($data);
        return $vendor;
    }

    // ---- purchase requests ----------------------------------------------

    public function paginateRequests(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return PurchaseRequest::query()
            ->with(['requester:id,name', 'approval'])
            ->search($f['q'] ?? null)
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findRequest(int $id): PurchaseRequest
    {
        return PurchaseRequest::with([
            'requester:id,name', 'items',
            'approval.actions.approver:id,name', 'approval.workflow:id,name,approver_ids',
        ])->findOrFail($id);
    }

    public function createRequest(array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['pr_no'] ??= $this->nextNumber(PurchaseRequest::class, 'pr_no', 'PR');
            $data['requested_by'] ??= auth()->id();
            $data['status'] = 'draft';
            $pr = PurchaseRequest::create($data);
            $this->syncRequestItems($pr, $items);
            return $this->findRequest($pr->id);
        });
    }

    public function updateRequest(PurchaseRequest $pr, array $data): PurchaseRequest
    {
        if (!in_array($pr->status, ['draft', 'rejected'], true)) {
            throw new RuntimeException('Only a draft or rejected request can be edited.');
        }
        return DB::transaction(function () use ($pr, $data) {
            $items = $data['items'] ?? null;
            unset($data['items'], $data['status']);
            $pr->update($data);
            if (is_array($items)) $this->syncRequestItems($pr, $items);
            return $this->findRequest($pr->id);
        });
    }

    /** Submit a PR for approval; auto-approves when no workflow matches its total. */
    public function submitRequest(PurchaseRequest $pr): PurchaseRequest
    {
        if (!in_array($pr->status, ['draft', 'rejected'], true)) {
            throw new RuntimeException('This request has already been submitted.');
        }
        return DB::transaction(function () use ($pr) {
            $approval = $this->approvals->initiate($pr, 'purchase_request', (float) $pr->estimated_total);
            $pr->forceFill(['status' => $approval ? 'submitted' : 'approved'])->save();
            return $this->findRequest($pr->id);
        });
    }

    /** Turn an approved PR into a draft PO for a vendor, copying its lines. */
    public function convertRequestToOrder(PurchaseRequest $pr, int $vendorId): PurchaseOrder
    {
        if ($pr->status !== 'approved') throw new RuntimeException('Only an approved request can be converted.');
        if ($pr->converted_po_id) throw new RuntimeException('This request was already converted to PO #'.$pr->converted_po_id.'.');

        return DB::transaction(function () use ($pr, $vendorId) {
            $po = $this->createOrder([
                'vendor_id' => $vendorId,
                'purchase_request_id' => $pr->id,
                'order_date' => now()->toDateString(),
                'items' => $pr->items->map(fn ($it) => [
                    'product_id' => $it->product_id, 'name' => $it->name,
                    'quantity' => (float) $it->quantity, 'unit_price' => (float) $it->estimated_price,
                ])->all(),
            ]);
            $pr->forceFill(['status' => 'converted', 'converted_po_id' => $po->id])->save();
            return $po;
        });
    }

    private function syncRequestItems(PurchaseRequest $pr, array $items): void
    {
        $pr->items()->delete();
        $total = 0.0;
        foreach (array_values($items) as $i => $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) ($line['estimated_price'] ?? 0);
            $total += $qty * $price;
            $pr->items()->create([
                'company_id' => $pr->company_id,
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Item',
                'quantity' => $qty, 'estimated_price' => $price,
                'note' => $line['note'] ?? null, 'sort_order' => $i,
            ]);
        }
        $pr->forceFill(['estimated_total' => round($total, 2)])->save();
    }

    // ---- purchase orders -------------------------------------------------

    public function paginateOrders(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['vendor:id,name', 'warehouse:id,name', 'approval'])
            ->search($f['q'] ?? null)
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['vendor_id']), fn ($q) => $q->where('vendor_id', $f['vendor_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrder(int $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'vendor:id,name,vendor_no', 'warehouse:id,name,code', 'creator:id,name',
            'items' => fn ($q) => $q->with('taxRate:id,name,rate'),
            'approval.actions.approver:id,name', 'approval.workflow:id,name,approver_ids',
        ])->findOrFail($id);
    }

    public function createOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['po_no'] ??= $this->nextNumber(PurchaseOrder::class, 'po_no', 'PO');
            $data['order_date'] ??= now()->toDateString();
            $data['created_by'] ??= auth()->id();
            $data['status'] = 'draft';
            $po = PurchaseOrder::create($data);
            $this->syncOrderItems($po, $items);
            return $this->findOrder($po->id);
        });
    }

    public function updateOrder(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if (!in_array($po->status, ['draft'], true)) {
            throw new RuntimeException('Only a draft purchase order can be edited.');
        }
        return DB::transaction(function () use ($po, $data) {
            $items = $data['items'] ?? null;
            unset($data['items'], $data['status']);
            $po->update($data);
            if (is_array($items)) $this->syncOrderItems($po, $items);
            return $this->findOrder($po->id);
        });
    }

    /** Send a PO for approval; auto-confirms when no workflow matches its total. */
    public function confirmOrder(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') throw new RuntimeException('Only a draft order can be confirmed.');
        return DB::transaction(function () use ($po) {
            $approval = $this->approvals->initiate($po, 'purchase_order', (float) $po->grand_total);
            $po->forceFill(['status' => $approval ? 'submitted' : 'confirmed'])->save();
            return $this->findOrder($po->id);
        });
    }

    /** Receive outstanding quantities into the PO's warehouse, posting stock movements. */
    public function receiveOrder(PurchaseOrder $po, ?array $lines = null): PurchaseOrder
    {
        if (!in_array($po->status, ['confirmed', 'received'], true)) {
            throw new RuntimeException('Only a confirmed order can be received.');
        }
        if (!$po->warehouse_id) throw new RuntimeException('Set a destination warehouse before receiving.');

        return DB::transaction(function () use ($po, $lines) {
            // Lock the order and re-read its lines INSIDE the transaction. `received_quantity`
            // was written as read + take against a relation loaded before the transaction, so
            // two concurrent partial receives of 40 and 60 on a 100-unit line both read 0 and
            // one update was lost: the line showed 60 received while stock had been posted
            // twice, leaving the PO apparently 40 short and receivable again. Locking the PO
            // serialises receives for that order, which is what makes read-then-write safe.
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $po->load('items');

            $byItem = collect($lines ?? [])->keyBy('item_id');
            foreach ($po->items as $it) {
                $outstanding = (float) $it->quantity - (float) $it->received_quantity;
                if ($outstanding <= 0) continue;
                // Receive the requested amount for this line, or all outstanding by default.
                $take = $lines ? (float) ($byItem[$it->id]['quantity'] ?? 0) : $outstanding;
                $take = min($take, $outstanding);
                if ($take <= 0) continue;

                if ($it->product_id) {
                    $this->inventory->receive($it->product_id, $po->warehouse_id, $take, [
                        'type' => 'purchase', 'unit_cost' => (float) $it->unit_price,
                        'reference' => $po->po_no,
                        'related_type' => PurchaseOrder::class, 'related_id' => $po->id,
                    ]);
                }
                $it->forceFill(['received_quantity' => (float) $it->received_quantity + $take])->save();
            }

            $po->refresh()->load('items');
            $po->forceFill([
                'status' => $po->isFullyReceived() ? 'received' : 'confirmed',
                'received_at' => $po->isFullyReceived() ? now() : $po->received_at,
            ])->save();
            return $this->findOrder($po->id);
        });
    }

    public function closeOrder(PurchaseOrder $po): PurchaseOrder
    {
        if (!in_array($po->status, ['confirmed', 'received'], true)) {
            throw new RuntimeException('Only a confirmed or received order can be closed.');
        }
        $po->forceFill(['status' => 'closed'])->save();
        return $this->findOrder($po->id);
    }

    public function cancelOrder(PurchaseOrder $po): PurchaseOrder
    {
        if (in_array($po->status, ['received', 'closed'], true)) {
            throw new RuntimeException('A received or closed order cannot be cancelled.');
        }
        $po->forceFill(['status' => 'cancelled'])->save();
        return $this->findOrder($po->id);
    }

    private function syncOrderItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();
        $taxCache = [];
        $resolveTax = function (?int $id) use (&$taxCache): array {
            if (!$id) return [0.0, false, null];
            if (!array_key_exists($id, $taxCache)) {
                $tr = TaxRate::find($id);
                $taxCache[$id] = $tr ? [(float) $tr->rate, (bool) $tr->is_inclusive, $tr->id] : [0.0, false, null];
            }
            return $taxCache[$id];
        };
        $productTax = Product::whereIn('id', collect($items)->pluck('product_id')->filter()->all())->pluck('tax_rate_id', 'id');

        foreach (array_values($items) as $i => $line) {
            $taxRateId = $line['tax_rate_id'] ?? ($productTax[$line['product_id'] ?? null] ?? null);
            [$rate, $inclusive, $trId] = $resolveTax($taxRateId);
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) ($line['unit_price'] ?? 0);
            $disc = (float) ($line['discount_pct'] ?? 0);
            [$lineTotal, $taxAmount] = SalesDocumentItem::computeLine($qty, $price, $disc, $rate, $inclusive);

            $po->items()->create([
                'company_id' => $po->company_id,
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Item',
                'description' => $line['description'] ?? null,
                'quantity' => $qty, 'unit_price' => $price, 'discount_pct' => $disc,
                'tax_rate_id' => $trId, 'tax_amount' => $taxAmount, 'line_total' => $lineTotal,
                'sort_order' => $i,
            ]);
        }
        $po->recomputeTotals();
    }

    // ---- approval acting -------------------------------------------------

    /** Act on a pending approval and reflect the outcome on the underlying PR/PO. */
    /**
     * One transaction around the approval AND the document status it drives. Previously
     * ApprovalService::act() committed in its own transaction and the document write was a
     * separate, unwrapped statement: if that write failed, the approval was recorded but the
     * PO stayed `submitted` — it could never be received (receiveOrder requires `confirmed`)
     * and never re-approved (act() refuses an already-approved request), i.e. permanently
     * stuck with no way forward.
     */
    public function actOnApproval(ApprovalRequest $request, string $action, ?string $comment): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $action, $comment) {
            return $this->applyApproval($request, $action, $comment);
        });
    }

    private function applyApproval(ApprovalRequest $request, string $action, ?string $comment): ApprovalRequest
    {
        $request = $this->approvals->act($request, $action, $comment);

        if ($request->status !== 'pending') {
            $doc = $request->approvable()->first();
            if ($doc instanceof PurchaseRequest) {
                $doc->forceFill(['status' => $request->status === 'approved' ? 'approved' : 'rejected'])->save();
            } elseif ($doc instanceof PurchaseOrder) {
                // A rejected PO returns to draft so it can be revised and re-sent.
                $doc->forceFill(['status' => $request->status === 'approved' ? 'confirmed' : 'draft'])->save();
            }
        }
        return $request->load(['actions.approver:id,name', 'workflow:id,name,approver_ids', 'approvable']);
    }

    // ---- workflows -------------------------------------------------------

    public function workflows(): \Illuminate\Support\Collection
    {
        return ApprovalWorkflow::orderBy('document_type')->orderBy('min_amount')->get();
    }

    public function createWorkflow(array $data): ApprovalWorkflow
    {
        return ApprovalWorkflow::create($data);
    }

    public function updateWorkflow(ApprovalWorkflow $wf, array $data): ApprovalWorkflow
    {
        $wf->update($data);
        return $wf;
    }

    // ---- stats -----------------------------------------------------------

    public function stats(): array
    {
        return [
            'vendors'          => Vendor::where('status', 'active')->count(),
            'pending_requests' => PurchaseRequest::where('status', 'submitted')->count(),
            'open_orders'      => PurchaseOrder::whereIn('status', ['draft', 'submitted', 'confirmed'])->count(),
            'awaiting_receipt' => PurchaseOrder::where('status', 'confirmed')->count(),
            'my_approvals'     => $this->approvals->pendingFor(auth()->id())->count(),
            'spend_mtd'        => (float) PurchaseOrder::whereIn('status', ['confirmed', 'received', 'closed'])
                ->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('grand_total'),
        ];
    }

    private function nextNumber(string $modelClass, string $column, string $prefix): string
    {
        $companyId = auth()->user()?->company_id;
        $q = $modelClass::withoutGlobalScopes();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $q = $q->withTrashed();
        }
        $last = $q->where('company_id', $companyId)->where($column, 'like', $prefix.'-%')
            ->orderByRaw("CAST(SUBSTRING({$column}, ?) AS UNSIGNED) DESC", [strlen($prefix) + 2])
            ->value($column);
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }
}
