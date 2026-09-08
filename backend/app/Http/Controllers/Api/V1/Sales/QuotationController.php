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

    /** Render the quotation as a downloadable PDF. */
    public function pdf(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $quote = Quotation::with(['items', 'customer', 'owner'])->findOrFail($id);
        $company = \App\Models\Company::find($quote->company_id);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quotation', ['q' => $quote, 'company' => $company]);
        return $pdf->download("quote-{$quote->quote_no}.pdf");
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
        try {
            $result = $this->sales->setQuotationStatus($quote, $data['status']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new QuotationResource($result), 'Status updated');
    }

    /** Submit a draft quotation into the approval workflow (if one applies to its amount). */
    public function submitForApproval(int $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        if ($quote->status !== 'draft') {
            return $this->error('Only a draft quotation can be submitted for approval.', 422);
        }
        $request = app(\App\Services\ApprovalService::class)->initiate($quote, 'quotation', (float) $quote->grand_total);
        if (!$request) {
            return $this->error('No approval workflow applies to this quotation — you can send it directly.', 422);
        }
        $quote->forceFill(['status' => 'pending_approval'])->save();
        return $this->success(new QuotationResource($quote->fresh()), 'Submitted for approval');
    }

    public function send(int $id): JsonResponse
    {
        $quote = Quotation::findOrFail($id);
        if ($quote->status === 'pending_approval') {
            return $this->error('This quotation is awaiting approval and cannot be sent yet.', 422);
        }
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
