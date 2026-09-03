<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerCreditRequest;
use App\Http\Resources\CustomerCreditResource;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Services\CreditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CustomerCreditController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q'           => 'nullable|string|max:191',
            'customer_id' => 'nullable|integer',
            'status'      => 'nullable|string|in:all,available,open,applied,void',
            'source'      => 'nullable|string|in:overpayment,invoice_adjustment,manual',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        return $this->paginated(
            $this->credits->paginate($f, (int) ($f['per_page'] ?? 25)),
            CustomerCreditResource::class
        );
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->credits->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'sources'  => CustomerCredit::SOURCES,
            'statuses' => CustomerCredit::STATUSES,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new CustomerCreditResource($this->credits->find($id)));
    }

    /** Issue a credit note by hand (goodwill, returns, corrections). */
    public function store(StoreCustomerCreditRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['source'] = 'manual';
        $data['currency'] = $data['currency'] ?? 'USD';

        try {
            $credit = $this->credits->issue($data);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new CustomerCreditResource($this->credits->find($credit->id)), 'Credit issued', 201);
    }

    public function void(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $credit = CustomerCredit::findOrFail($id);

        try {
            $voided = $this->credits->void($credit, $data['reason'] ?? null);
        } catch (QueryException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new CustomerCreditResource($voided), 'Credit voided');
    }

    /** Credits with something left, for the picker on an invoice. */
    public function availableForInvoice(int $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if (!$invoice->customer_id) return $this->success([]);

        return $this->success(
            $this->credits->availableFor($invoice->customer_id, $invoice->currency)
                ->map(fn ($c) => [
                    'id' => $c->id, 'credit_no' => $c->credit_no, 'source' => $c->source,
                    'currency' => $c->currency, 'remaining' => $c->remaining,
                ])
        );
    }

    /** Apply a credit to an invoice. Omit `amount` to apply as much as both sides allow. */
    public function applyToInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $data = $request->validate([
            'credit_id' => 'required|integer',
            'amount'    => 'nullable|numeric|min:0.01',
        ]);

        // findOrFail through the company-scoped models is the tenancy check: another
        // tenant's credit or invoice id simply is not found.
        Invoice::findOrFail($invoiceId);
        CustomerCredit::findOrFail($data['credit_id']);

        try {
            $application = $this->credits->apply($data['credit_id'], $invoiceId, $data['amount'] ?? null);
        } catch (QueryException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'id' => $application->id,
            'amount' => (float) $application->amount,
            'credit' => ['id' => $application->credit->id, 'credit_no' => $application->credit->credit_no],
            'invoice' => ['id' => $application->invoice->id, 'invoice_no' => $application->invoice->invoice_no],
        ], 'Credit applied', 201);
    }
}
