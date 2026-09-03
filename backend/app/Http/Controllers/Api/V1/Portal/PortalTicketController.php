<?php
namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalTicketResource;
use App\Models\Contact;
use App\Models\Ticket;
use App\Services\HelpdeskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every query explicitly filters by company_id AND customer_id from the authenticated Contact —
 * see PortalInvoiceController for why this never relies on BelongsToCompany's global scope.
 * Ticket creation/replies reuse HelpdeskService (already guard-agnostic for authorType=customer),
 * but every ticket is re-fetched through this controller's own scoped query, never
 * HelpdeskService::find(), so an ownership check always runs before a reply is shown or accepted.
 */
class PortalTicketController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): JsonResponse
    {
        $contact = $this->contact();
        $tickets = $this->scoped($contact)->orderByDesc('id')->paginate(25);
        return $this->paginated($tickets, PortalTicketResource::class);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = $this->findOwned($id);
        if (!$ticket) return $this->error('Ticket not found', 404);

        $ticket->load(['replies' => fn ($q) => $q->where('is_internal', false)->orderBy('id')]);
        return $this->success(new PortalTicketResource($ticket));
    }

    public function store(Request $request): JsonResponse
    {
        $contact = $this->contact();
        $data = $request->validate([
            'subject'     => 'required|string|max:191',
            'description' => 'nullable|string|max:10000',
            'priority'    => 'nullable|in:'.implode(',', Ticket::PRIORITIES),
        ]);

        $ticket = $this->helpdesk->create($data + [
            'company_id'      => $contact->company_id,
            'customer_id'     => $contact->customer_id,
            'requester_name'  => $contact->name,
            'requester_email' => $contact->email,
            'channel'         => 'web',
        ]);

        return $this->success(new PortalTicketResource($ticket), 'Ticket created', 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->findOwned($id);
        if (!$ticket) return $this->error('Ticket not found', 404);

        $data = $request->validate(['body' => 'required|string|max:10000']);
        $this->helpdesk->reply($ticket, $data['body'], false, 'customer');

        $ticket->refresh()->load(['replies' => fn ($q) => $q->where('is_internal', false)->orderBy('id')]);
        return $this->success(new PortalTicketResource($ticket), 'Reply added', 201);
    }

    private function contact(): Contact
    {
        /** @var Contact $contact */
        $contact = auth('portal')->user();
        return $contact;
    }

    private function scoped(Contact $contact)
    {
        return Ticket::withoutGlobalScope('company')
            ->where('company_id', $contact->company_id)
            ->where('customer_id', $contact->customer_id);
    }

    private function findOwned(int $id): ?Ticket
    {
        return $this->scoped($this->contact())->find($id);
    }
}
