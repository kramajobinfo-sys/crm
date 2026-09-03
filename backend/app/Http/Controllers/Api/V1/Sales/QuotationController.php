<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Requests\Sales\UpdateQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\Quotation;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

class QuotationController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,draft,sent,accepted,rejected,expired,converted',
            'customer_id' => 'nullable|integer',
            'owner_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->sales->paginateQuotations($f, (int) ($f['per_page'] ?? 25)), QuotationResource::class);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new QuotationResource($this->sales->findQuotation($id)));
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        return $this->success(new QuotationResource($this->sales->createQuotation($request->validated())), 'Quotation created', 201);
    }

    public function update(UpdateQuotationRequest $request, int $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        return $this->success(new QuotationResource($this->sales->updateQuotation($quote, $request->validated())), 'Quotation updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Quotation::findOrFail($id)->delete();
        return $this->success(null, 'Quotation deleted');
    }

    public function setStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|string|in:draft,sent,accepted,rejected,expired']);
        $quote = Quotation::findOrFail($id);
        return $this->success(new QuotationResource($this->sales->setQuotationStatus($quote, $data['status'])), 'Status updated');
    }

    public function send(int $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        try {
            $result = $this->sales->sendQuotation($quote);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success([
            'quotation' => new QuotationResource($result['quotation']), 'emailed' => $result['emailed'],
        ], $result['emailed'] ? 'Quotation sent and emailed to the customer' : 'Quotation marked as sent (email could not be delivered)');
    }

    public function convert(int $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        try {
            $order = $this->sales->convertQuotationToOrder($quote);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new SalesOrderResource($order), 'Converted to sales order', 201);
    }
}
