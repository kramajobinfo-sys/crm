<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Http\Requests\Sales\StorePaymentRequest;
use App\Http\Requests\Sales\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,draft,issued,partially_paid,paid,void',
            'customer_id' => 'nullable|integer',
            'owner_id' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->sales->paginateInvoices($f, (int) ($f['per_page'] ?? 25)), InvoiceResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->sales->stats());
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new InvoiceResource($this->sales->findInvoice($id)));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        return $this->success(new InvoiceResource($this->sales->createInvoice($request->validated())), 'Invoice created', 201);
    }

    public function update(UpdateInvoiceRequest $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        return $this->success(new InvoiceResource($this->sales->updateInvoice($invoice, $request->validated())), 'Invoice updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Invoice::findOrFail($id)->delete();
        return $this->success(null, 'Invoice deleted');
    }

    public function setStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|string|in:draft,issued,void']);
        $invoice = Invoice::findOrFail($id);
        return $this->success(new InvoiceResource($this->sales->setInvoiceStatus($invoice, $data['status'])), 'Status updated');
    }

    /** Record a payment against this invoice; the invoice status re-derives from its payments. */
    public function pay(StorePaymentRequest $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $payment = $this->sales->recordPayment($invoice, $request->validated());
        return $this->success([
            'payment' => new PaymentResource($payment),
            'invoice' => new InvoiceResource($this->sales->findInvoice($invoice->id)),
        ], 'Payment recorded', 201);
    }
}
