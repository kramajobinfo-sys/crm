<?php
namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseService $purchase) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,draft,submitted,confirmed,received,closed,cancelled',
            'vendor_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->purchase->paginateOrders($f, (int) ($f['per_page'] ?? 25)), PurchaseOrderResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->purchase->stats());
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new PurchaseOrderResource($this->purchase->findOrder($id)));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        return $this->success(new PurchaseOrderResource($this->purchase->createOrder($request->validated())), 'Order created', 201);
    }

    public function update(StorePurchaseOrderRequest $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        try {
            $po = $this->purchase->updateOrder($po, $request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new PurchaseOrderResource($po), 'Order updated');
    }

    public function destroy(int $id): JsonResponse
    {
        PurchaseOrder::findOrFail($id)->delete();
        return $this->success(null, 'Order deleted');
    }

    public function confirm(int $id): JsonResponse
    {
        return $this->action($id, fn (PurchaseOrder $po) => $this->purchase->confirmOrder($po), 'Order confirmed');
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'nullable|array',
            'lines.*.item_id' => 'required_with:lines|integer',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0',
        ]);
        return $this->action($id, fn (PurchaseOrder $po) => $this->purchase->receiveOrder($po, $data['lines'] ?? null), 'Order received');
    }

    public function close(int $id): JsonResponse
    {
        return $this->action($id, fn (PurchaseOrder $po) => $this->purchase->closeOrder($po), 'Order closed');
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->action($id, fn (PurchaseOrder $po) => $this->purchase->cancelOrder($po), 'Order cancelled');
    }

    private function action(int $id, \Closure $fn, string $message): JsonResponse
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);
        try {
            $po = $fn($po);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new PurchaseOrderResource($po), $message);
    }
}
