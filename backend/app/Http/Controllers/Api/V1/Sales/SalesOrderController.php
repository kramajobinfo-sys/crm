<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,draft,confirmed,processing,fulfilled,cancelled',
            'customer_id' => 'nullable|integer',
            'owner_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->sales->paginateOrders($f, (int) ($f['per_page'] ?? 25)), SalesOrderResource::class);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new SalesOrderResource($this->sales->findOrder($id)));
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        return $this->success(new SalesOrderResource($this->sales->createOrder($request->validated())), 'Order created', 201);
    }

    public function update(UpdateSalesOrderRequest $request, int $id): JsonResponse
    {
        $order = SalesOrder::findOrFail($id);
        return $this->success(new SalesOrderResource($this->sales->updateOrder($order, $request->validated())), 'Order updated');
    }

    public function destroy(int $id): JsonResponse
    {
        SalesOrder::findOrFail($id)->delete();
        return $this->success(null, 'Order deleted');
    }

    public function setStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|string|in:draft,confirmed,processing,fulfilled,cancelled']);
        $order = SalesOrder::findOrFail($id);
        try {
            $result = $this->sales->setOrderStatus($order, $data['status']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new SalesOrderResource($result), 'Status updated');
    }

    public function convert(int $id): JsonResponse
    {
        $order = SalesOrder::findOrFail($id);
        try {
            $invoice = $this->sales->convertOrderToInvoice($order);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new InvoiceResource($invoice), 'Converted to invoice', 201);
    }
}
