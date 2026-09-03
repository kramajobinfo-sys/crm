<?php
namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalQuotationResource;
use App\Models\Contact;
use App\Models\Quotation;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Database\QueryException;

/**
 * Every query explicitly filters by company_id AND customer_id from the authenticated Contact —
 * see PortalInvoiceController for why this never relies on BelongsToCompany's global scope.
 * Draft quotations are never shown here — a customer only ever sees what staff has actually sent.
 */
class PortalQuotationController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $contact = $this->contact();
        $quotations = $this->scoped($contact)->orderByDesc('id')->paginate(25);
        return $this->paginated($quotations, PortalQuotationResource::class);
    }

    public function show(int $id): JsonResponse
    {
        $quotation = $this->scoped($this->contact())->with('items')->find($id);
        if (!$quotation) return $this->error('Quotation not found', 404);
        return $this->success(new PortalQuotationResource($quotation));
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $quotation = $this->scoped($this->contact())->find($id);
        if (!$quotation) return $this->error('Quotation not found', 404);

        $data = $request->validate([
            'signed_name' => 'required|string|max:191',
            'signature_data' => 'required|string|max:200000',
        ]);

        try {
            $signed = $this->sales->signQuotation($quotation, $data['signed_name'], $data['signature_data'], $request->ip());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new PortalQuotationResource($signed), 'Quotation signed');
    }

    private function contact(): Contact
    {
        /** @var Contact $contact */
        $contact = auth('portal')->user();
        return $contact;
    }

    private function scoped(Contact $contact)
    {
        return Quotation::withoutGlobalScope('company')
            ->where('company_id', $contact->company_id)
            ->where('customer_id', $contact->customer_id)
            ->where('status', '!=', 'draft');
    }
}
