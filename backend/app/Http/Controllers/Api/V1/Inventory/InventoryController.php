<?php
namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreTransferRequest;
use App\Http\Resources\StockItemResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockTransferResource;
use App\Models\Barcode;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function stock(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'warehouse_id' => 'nullable|integer',
            'filter' => 'nullable|string|in:low,out',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->inventory->paginateStock($f, (int) ($f['per_page'] ?? 25)), StockItemResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->inventory->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id','name','code','is_default']),
            'movement_types' => StockMovement::TYPES,
            'transfer_statuses' => StockTransfer::STATUSES,
            'barcode_types' => Barcode::TYPES,
            'next_transfer_no' => $this->inventory->nextTransferNo(),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $f = $request->validate([
            'product_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->inventory->paginateMovements($f, (int) ($f['per_page'] ?? 25)), StockMovementResource::class);
    }

    public function adjust(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'product_id'   => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'warehouse_id' => ['required','integer', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'value'        => ['required','numeric'],
            'mode'         => ['required','string','in:set,delta'],
            'note'         => ['nullable','string','max:2000'],
        ]);
        try {
            $mv = $this->inventory->adjust($data['product_id'], $data['warehouse_id'], (float) $data['value'], $data['mode'], $data['note'] ?? null);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new StockMovementResource($mv->load(['product:id,sku,name', 'warehouse:id,name', 'user:id,name'])), 'Stock adjusted', 201);
    }

    /** Receive (positive) or issue (negative) stock at a warehouse. */
    public function move(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'product_id'   => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'warehouse_id' => ['required','integer', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'direction'    => ['required','string','in:receive,issue'],
            'quantity'     => ['required','numeric','min:0.01'],
            'unit_cost'    => ['nullable','numeric','min:0'],
            'reference'    => ['nullable','string','max:96'],
            'note'         => ['nullable','string','max:2000'],
        ]);
        $opts = array_filter([
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
        ], fn ($v) => $v !== null);
        try {
            $mv = $data['direction'] === 'receive'
                ? $this->inventory->receive($data['product_id'], $data['warehouse_id'], (float) $data['quantity'], $opts)
                : $this->inventory->issue($data['product_id'], $data['warehouse_id'], (float) $data['quantity'], $opts);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new StockMovementResource($mv->load(['product:id,sku,name', 'warehouse:id,name', 'user:id,name'])), 'Stock updated', 201);
    }

    // ---- transfers -------------------------------------------------------

    public function transfers(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status' => 'nullable|string|in:all,draft,in_transit,received,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->inventory->paginateTransfers($f, (int) ($f['per_page'] ?? 25)), StockTransferResource::class);
    }

    public function showTransfer(int $id): JsonResponse
    {
        return $this->success(new StockTransferResource($this->inventory->findTransfer($id)));
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->inventory->createTransfer($request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new StockTransferResource($transfer), 'Transfer created', 201);
    }

    public function transferAction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => 'required|string|in:ship,receive,cancel']);
        $transfer = StockTransfer::with('items')->findOrFail($id);
        try {
            $transfer = match ($data['action']) {
                'ship'    => $this->inventory->shipTransfer($transfer),
                'receive' => $this->inventory->receiveTransfer($transfer),
                'cancel'  => $this->inventory->cancelTransfer($transfer),
            };
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new StockTransferResource($transfer), 'Transfer updated');
    }

    // ---- barcodes --------------------------------------------------------

    public function barcodes(int $productId): JsonResponse
    {
        return $this->success($this->inventory->barcodes($productId)->map(fn ($b) => [
            'id' => $b->id, 'barcode' => $b->barcode, 'type' => $b->type, 'is_primary' => (bool) $b->is_primary,
        ]));
    }

    public function storeBarcode(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'product_id' => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'barcode'    => ['required','string','max:64', Rule::unique('barcodes','barcode')->where('company_id',$companyId)],
            'type'       => ['nullable', Rule::in(Barcode::TYPES)],
            'is_primary' => ['nullable','boolean'],
        ]);
        $b = $this->inventory->createBarcode($data);
        return $this->success(['id' => $b->id, 'barcode' => $b->barcode, 'type' => $b->type, 'is_primary' => (bool) $b->is_primary], 'Barcode added', 201);
    }

    public function destroyBarcode(int $id): JsonResponse
    {
        Barcode::findOrFail($id)->delete();
        return $this->success(null, 'Barcode deleted');
    }
}
