<?php
namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalInvoiceResource;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every query here explicitly filters by company_id AND customer_id from the authenticated
 * Contact, bypassing BelongsToCompany's global scope entirely — see Contact model docblock.
 */
class PortalInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Contact $contact */
        $contact = auth('portal')->user();

        $invoices = Invoice::withoutGlobalScope('company')
            ->where('company_id', $contact->company_id)
            ->where('customer_id', $contact->customer_id)
            ->orderByDesc('issue_date')
            ->paginate(25);

        return $this->paginated($invoices, PortalInvoiceResource::class);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Contact $contact */
        $contact = auth('portal')->user();

        $invoice = Invoice::withoutGlobalScope('company')
            ->where('company_id', $contact->company_id)
            ->where('customer_id', $contact->customer_id)
            ->with('items')
            ->find($id);
        if (!$invoice) return $this->error('Invoice not found', 404);

        return $this->success(new PortalInvoiceResource($invoice));
    }
}
