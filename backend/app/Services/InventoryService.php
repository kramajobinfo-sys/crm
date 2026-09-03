<?php
namespace App\Services;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    // ---- warehouses ------------------------------------------------------

    public function warehouses(): Collection
    {
        return Warehouse::withCount('stockItems')->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function createWarehouse(array $data): Warehouse
    {
        if (!empty($data['is_default'])) Warehouse::where('is_default', true)->update(['is_default' => false]);
        return Warehouse::create($data);
    }

    public function updateWarehouse(Warehouse $w, array $data): Warehouse
    {
        if (!empty($data['is_default'])) Warehouse::where('id', '!=', $w->id)->where('is_default', true)->update(['is_default' => false]);
        $w->update($data);
        return $w;
    }

    // ---- stock levels ----------------------------------------------------

    public function paginateStock(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return StockItem::query()
            ->with(['product:id,sku,name,unit,reorder_level,track_inventory', 'warehouse:id,name,code'])
            ->when(!empty($f['warehouse_id']), fn ($q) => $q->where('warehouse_id', $f['warehouse_id']))
            ->when(!empty($f['q']), fn ($q) => $q->whereHas('product', fn ($p) =>
                $p->where('name', 'like', '%'.$f['q'].'%')->orWhere('sku', 'like', '%'.$f['q'].'%')))
            ->when(($f['filter'] ?? null) === 'low', fn ($q) => $q->lowStock())
            ->when(($f['filter'] ?? null) === 'out', fn ($q) => $q->where('quantity', '<=', 0))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function stats(): array
    {
        return [
            'warehouses'   => Warehouse::where('is_active', true)->count(),
            'tracked_skus' => StockItem::distinct('product_id')->count('product_id'),
            'low_stock'    => StockItem::lowStock()->count(),
            'out_of_stock' => StockItem::where('quantity', '<=', 0)->count(),
            'stock_value'  => (float) StockItem::query()
                ->join('products', 'products.id', '=', 'stock_items.product_id')
                ->where('stock_items.company_id', auth()->user()->company_id)
                ->selectRaw('COALESCE(SUM(stock_items.quantity * products.cost_price),0) as v')->value('v'),
        ];
    }

    public function paginateMovements(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,name', 'user:id,name'])
            ->when(!empty($f['product_id']), fn ($q) => $q->where('product_id', $f['product_id']))
            ->when(!empty($f['warehouse_id']), fn ($q) => $q->where('warehouse_id', $f['warehouse_id']))
            ->when(!empty($f['type']), fn ($q) => $q->where('type', $f['type']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    // ---- the ledger: single source of truth for on-hand ------------------

    /**
     * Apply one signed movement to (product, warehouse): upsert the stock row, write the
     * ledger entry with the resulting balance, and return it. All stock changes funnel here.
     */
    public function applyMovement(int $productId, int $warehouseId, string $type, float $signedQty, array $opts = []): StockMovement
    {
        return DB::transaction(function () use ($productId, $warehouseId, $type, $signedQty, $opts) {
            $companyId = auth()->user()->company_id;

            // Ensure the row exists — safe on its own because of unique(product_id,
            // warehouse_id) — then re-read it under a ROW LOCK. Without the lock, two
            // concurrent movements both read the same on-hand, both computed the same new
            // balance and both wrote it: 100 issued twice by 10 ended at 90 instead of 80,
            // with two ledger rows each stamped balance_after=90. Summing the ledger then
            // disagreed with stock_items permanently, and nothing surfaced the drift.
            StockItem::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['company_id' => $companyId, 'quantity' => 0]
            );
            $item = StockItem::where('product_id', $productId)->where('warehouse_id', $warehouseId)
                ->lockForUpdate()->firstOrFail();

            $balance = round((float) $item->quantity + $signedQty, 2);
            $item->forceFill(['quantity' => $balance]);
            if (isset($opts['unit_cost']) && $signedQty > 0) $item->average_cost = $opts['unit_cost'];
            $item->save();

            return StockMovement::create([
                'company_id' => $companyId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $signedQty,
                'balance_after' => $balance,
                'unit_cost' => $opts['unit_cost'] ?? 0,
                'reference' => $opts['reference'] ?? null,
                'note' => $opts['note'] ?? null,
                'related_type' => $opts['related_type'] ?? null,
                'related_id' => $opts['related_id'] ?? null,
                'user_id' => auth()->id(),
                'occurred_at' => $opts['occurred_at'] ?? now(),
            ]);
        });
    }

    /** Manual adjustment: `mode=set` targets an absolute on-hand, `mode=delta` adds/removes. */
    public function adjust(int $productId, int $warehouseId, float $value, string $mode, ?string $note = null): StockMovement
    {
        // The read has to happen inside the same transaction as the write, under the same
        // row lock applyMovement takes. Previously `$current` was read outside any
        // transaction, so `mode=set` silently broke its own absolute contract: if anything
        // moved the stock between the read and the write, the delta was computed against a
        // base that no longer existed and the on-hand landed somewhere other than $value.
        return DB::transaction(function () use ($productId, $warehouseId, $value, $mode, $note) {
            $current = (float) (StockItem::where('product_id', $productId)->where('warehouse_id', $warehouseId)
                ->lockForUpdate()->value('quantity') ?? 0);

            $delta = $mode === 'set' ? round($value - $current, 2) : round($value, 2);
            if ($delta == 0.0) throw new RuntimeException('That adjustment would not change the on-hand quantity.');

            // Nested DB::transaction becomes a savepoint, and re-taking a lock this
            // transaction already holds is a no-op.
            return $this->applyMovement($productId, $warehouseId, 'adjustment', $delta, ['note' => $note]);
        });
    }

    public function receive(int $productId, int $warehouseId, float $qty, array $opts = []): StockMovement
    {
        if ($qty <= 0) throw new RuntimeException('Received quantity must be greater than zero.');
        return $this->applyMovement($productId, $warehouseId, $opts['type'] ?? 'receipt', abs($qty), $opts);
    }

    public function issue(int $productId, int $warehouseId, float $qty, array $opts = []): StockMovement
    {
        if ($qty <= 0) throw new RuntimeException('Issued quantity must be greater than zero.');
        return $this->applyMovement($productId, $warehouseId, $opts['type'] ?? 'issue', -abs($qty), $opts);
    }

    // ---- transfers -------------------------------------------------------

    public function paginateTransfers(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'requester:id,name'])
            ->withCount('items')
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findTransfer(int $id): StockTransfer
    {
        return StockTransfer::with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code',
            'requester:id,name', 'items.product:id,sku,name'])->findOrFail($id);
    }

    public function createTransfer(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            if (($data['from_warehouse_id'] ?? null) == ($data['to_warehouse_id'] ?? null)) {
                throw new RuntimeException('Source and destination warehouses must differ.');
            }
            $data['transfer_no'] ??= $this->nextTransferNo();
            $data['transfer_date'] ??= now()->toDateString();
            $data['requested_by'] ??= auth()->id();
            $data['status'] = 'draft';
            $transfer = StockTransfer::create($data);
            $this->syncTransferItems($transfer, $items);
            return $this->findTransfer($transfer->id);
        });
    }

    public function updateTransfer(StockTransfer $transfer, array $data): StockTransfer
    {
        if ($transfer->status !== 'draft') throw new RuntimeException('Only a draft transfer can be edited.');
        return DB::transaction(function () use ($transfer, $data) {
            $items = $data['items'] ?? null;
            unset($data['items'], $data['status']);
            $transfer->update($data);
            if (is_array($items)) $this->syncTransferItems($transfer, $items);
            return $this->findTransfer($transfer->id);
        });
    }

    /** Draft → in_transit: take the goods out of the source warehouse. */
    public function shipTransfer(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'draft') throw new RuntimeException('Only a draft transfer can be shipped.');
        return DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $line) {
                $this->applyMovement($line->product_id, $transfer->from_warehouse_id, 'transfer_out', -abs((float) $line->quantity), [
                    'reference' => $transfer->transfer_no,
                    'related_type' => StockTransfer::class, 'related_id' => $transfer->id,
                ]);
            }
            $transfer->forceFill(['status' => 'in_transit', 'shipped_at' => now()])->save();
            return $this->findTransfer($transfer->id);
        });
    }

    /** In_transit → received: put the goods into the destination warehouse. */
    public function receiveTransfer(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'in_transit') throw new RuntimeException('Only a shipped transfer can be received.');
        return DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $line) {
                $this->applyMovement($line->product_id, $transfer->to_warehouse_id, 'transfer_in', abs((float) $line->quantity), [
                    'reference' => $transfer->transfer_no,
                    'related_type' => StockTransfer::class, 'related_id' => $transfer->id,
                ]);
            }
            $transfer->forceFill(['status' => 'received', 'received_at' => now()])->save();
            return $this->findTransfer($transfer->id);
        });
    }

    public function cancelTransfer(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status === 'received') throw new RuntimeException('A received transfer cannot be cancelled.');

        // ONE transaction around both the return movements and the status flip, and the
        // status is re-read under a lock. Previously the movements committed in an inner
        // transaction while the status was written outside it: if that write failed the
        // transfer stayed `in_transit`, so the operator retried and the goods were returned
        // to source a SECOND time — 50 units became 100 with two transfer_in rows for one
        // 50-unit transfer. shipTransfer/receiveTransfer both already wrapped the whole
        // operation; this was the odd one out.
        return DB::transaction(function () use ($transfer) {
            $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') throw new RuntimeException('This transfer is already cancelled.');
            if ($locked->status === 'received') throw new RuntimeException('A received transfer cannot be cancelled.');

            if ($locked->status === 'in_transit') {
                foreach ($locked->items as $line) {
                    $this->applyMovement($line->product_id, $locked->from_warehouse_id, 'transfer_in', abs((float) $line->quantity), [
                        'reference' => $locked->transfer_no.' (cancel)',
                        'related_type' => StockTransfer::class, 'related_id' => $locked->id,
                    ]);
                }
            }

            $locked->forceFill(['status' => 'cancelled'])->save();
            return $this->findTransfer($locked->id);
        });
    }

    private function syncTransferItems(StockTransfer $transfer, array $items): void
    {
        $transfer->items()->delete();
        $names = Product::whereIn('id', collect($items)->pluck('product_id')->filter()->all())->pluck('name', 'id');
        foreach (array_values($items) as $i => $line) {
            if (empty($line['product_id']) || ($line['quantity'] ?? 0) <= 0) continue;
            $transfer->items()->create([
                'company_id' => $transfer->company_id,
                'product_id' => $line['product_id'],
                'name' => $names[$line['product_id']] ?? 'Item',
                'quantity' => $line['quantity'],
                'sort_order' => $i,
            ]);
        }
    }

    public function nextTransferNo(string $prefix = 'TRF'): string
    {
        $companyId = auth()->user()?->company_id;
        $last = StockTransfer::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)->where('transfer_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(transfer_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('transfer_no');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }

    // ---- barcodes --------------------------------------------------------

    public function barcodes(int $productId): Collection
    {
        return Barcode::where('product_id', $productId)->orderByDesc('is_primary')->get();
    }

    public function createBarcode(array $data): Barcode
    {
        if (!empty($data['is_primary'])) {
            Barcode::where('product_id', $data['product_id'])->update(['is_primary' => false]);
        }
        return Barcode::create($data);
    }
}
