<?php
namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\StoreTicketRequest;
use App\Http\Requests\Helpdesk\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\HelpdeskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,open,new,pending,resolved,closed',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'category_id' => 'nullable|integer',
            'assigned_to' => 'nullable|string',
            'breaching' => 'nullable|string|in:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->helpdesk->paginate($f, (int) ($f['per_page'] ?? 25)), TicketResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->helpdesk->stats());
    }

    public function meta(Request $request): JsonResponse
    {
        return $this->success([
            'categories' => TicketCategory::where('is_active', true)->orderBy('name')->get(['id','name','code']),
            'sla_policies' => SlaPolicy::where('is_active', true)->orderBy('priority')->get(['id','name','priority','first_response_minutes','resolution_minutes']),
            'agents' => \App\Models\User::where('company_id', $request->user()->company_id)
                ->where('is_active', true)->orderBy('name')->get(['id','name']),
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
            'channels' => Ticket::CHANNELS,
            'next_ticket_no' => $this->helpdesk->nextTicketNo(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new TicketResource($this->helpdesk->find($id)));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        return $this->success(new TicketResource($this->helpdesk->create($request->validated())), 'Ticket created', 201);
    }

    public function update(UpdateTicketRequest $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        return $this->success(new TicketResource($this->helpdesk->update($ticket, $request->validated())), 'Ticket updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Ticket::findOrFail($id)->delete();
        return $this->success(null, 'Ticket deleted');
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:10000',
            'internal' => 'nullable|boolean',
            'author_type' => 'nullable|string|in:agent,customer',
        ]);
        $ticket = Ticket::findOrFail($id);
        $this->helpdesk->reply($ticket, $data['body'], (bool) ($data['internal'] ?? false), $data['author_type'] ?? 'agent');
        return $this->success(new TicketResource($this->helpdesk->find($id)), 'Reply added', 201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'user_id' => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
        ]);
        $ticket = Ticket::findOrFail($id);
        return $this->success(new TicketResource($this->helpdesk->assign($ticket, $data['user_id'] ?? null)), 'Ticket assigned');
    }

    public function setStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|string|in:new,open,pending,resolved,closed']);
        $ticket = Ticket::findOrFail($id);
        return $this->success(new TicketResource($this->helpdesk->setStatus($ticket, $data['status'])), 'Status updated');
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'escalated_to' => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'reason' => ['nullable','string','max:191'],
            'note' => ['nullable','string','max:2000'],
        ]);
        $ticket = Ticket::findOrFail($id);
        return $this->success(new TicketResource(
            $this->helpdesk->escalate($ticket, $data['escalated_to'] ?? null, $data['reason'] ?? null, $data['note'] ?? null)
        ), 'Ticket escalated');
    }
}
