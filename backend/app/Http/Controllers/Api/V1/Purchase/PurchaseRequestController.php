<?php
namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequestRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class PurchaseRequestController extends Controller
{
    public function __construct(private readonly PurchaseService $purchase) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,draft,submitted,approved,rejected,converted,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->purchase->paginateRequests($f, (int) ($f['per_page'] ?? 25)), PurchaseRequestResource::class);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new PurchaseRequestResource($this->purchase->findRequest($id)));
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        return $this->success(new PurchaseRequestResource($this->purchase->createRequest($request->validated())), 'Request created', 201);
    }

    public function update(StorePurchaseRequestRequest $request, int $id): JsonResponse
    {
        $pr = PurchaseRequest::findOrFail($id);
        try {
            $pr = $this->purchase->updateRequest($pr, $request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new PurchaseRequestResource($pr), 'Request updated');
    }

    public function destroy(int $id): JsonResponse
    {
        PurchaseRequest::findOrFail($id)->delete();
        return $this->success(null, 'Request deleted');
    }

    public function submit(int $id): JsonResponse
    {
        $pr = PurchaseRequest::findOrFail($id);
        try {
            $pr = $this->purchase->submitRequest($pr);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new PurchaseRequestResource($pr), 'Request submitted');
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'vendor_id' => ['required','integer', Rule::exists('vendors','id')->where('company_id',$companyId)],
        ]);
        $pr = PurchaseRequest::with('items')->findOrFail($id);
        try {
            $po = $this->purchase->convertRequestToOrder($pr, (int) $data['vendor_id']);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new PurchaseOrderResource($po), 'Converted to purchase order', 201);
    }
}
