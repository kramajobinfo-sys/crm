<?php
namespace App\Http\Controllers\Api\V1\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\DealResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class LeadController extends Controller
{
    private const MAX_UPLOAD_KB = 15360;   // 15 MB, same ceiling as chat media

    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q'         => 'nullable|string|max:191',
            'converted' => 'nullable|string|in:all,open,converted',
            'status_id' => 'nullable|integer',
            'source_id' => 'nullable|integer',
            'rating'    => 'nullable|string|in:hot,warm,cold',
            'owner_id'  => 'nullable|string',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated(
            $this->leads->paginate($filters, (int) ($filters['per_page'] ?? 25)),
            LeadResource::class
        );
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->leads->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'sources'  => LeadSource::where('is_active', true)->orderBy('name')->get(['id','name','code']),
            'statuses' => LeadStatus::orderBy('sort_order')->get(['id','name','code','color','is_won','is_lost','is_default']),
            'ratings'  => Lead::RATINGS,
            'next_lead_no' => $this->leads->nextLeadNo(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new LeadResource($this->leads->find($id)));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        return $this->success(new LeadResource($this->leads->create($request->validated())), 'Lead created', 201);
    }

    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        return $this->success(new LeadResource($this->leads->update($lead, $request->validated())), 'Lead updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Lead::findOrFail($id)->delete();
        return $this->success(null, 'Lead deleted');
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|string|in:note,call,email,meeting',
        ]);
        $lead = Lead::findOrFail($id);
        $entry = $this->leads->addNote($lead, $data['body'], $data['type'] ?? 'note');
        return $this->success([
            'id' => $entry->id, 'type' => $entry->type, 'title' => $entry->title, 'body' => $entry->body,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'occurred_human' => $entry->occurred_at?->diffForHumans(),
        ], 'Note added', 201);
    }

    /** Re-run assignment rules on demand. */
    public function assign(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        // Scoped to the caller's company, like every sibling owner_id/assigned_to rule.
        // Unscoped, this both leaks the existence of users in other tenants (the assigned
        // owner's name is echoed back in LeadResource) and lets a lead be owned by a foreigner.
        $data = $request->validate([
            'user_id' => ['nullable', 'integer',
                Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
        ]);

        if (array_key_exists('user_id', $data)) {
            $lead = $this->leads->update($lead, ['owner_id' => $data['user_id']]);
            return $this->success(new LeadResource($lead), 'Lead assigned');
        }

        $assigned = $this->leads->autoAssign($lead);
        return $assigned
            ? $this->success(new LeadResource($this->leads->find($id)), 'Assigned by rules')
            : $this->error('No assignment rule matched this lead.', 422);
    }

    /**
     * Convert to an Account, Contact, and optional Deal. Refuses an already-converted lead rather than
     * creating a duplicate account.
     */
    public function convert(Request $request, int $id): JsonResponse
    {
        $lead = Lead::with('addresses')->findOrFail($id);
        $companyId = $request->user()->company_id;
        $options = $request->validate([
            'account_mode' => 'nullable|string|in:new,existing',
            'account_id' => ['nullable','integer','required_if:account_mode,existing', Rule::exists('customers','id')->where('company_id',$companyId)->whereNull('deleted_at')],
            'account' => 'nullable|array',
            'account.name' => 'nullable|string|max:191',
            'account.type' => 'nullable|string|in:company,individual',
            'create_contact' => 'nullable|boolean',
            'contact' => 'nullable|array',
            'contact.name' => 'nullable|string|max:191',
            'contact.title' => 'nullable|string|max:128',
            'contact.email' => 'nullable|email|max:191',
            'contact.phone' => 'nullable|string|max:32',
            'contact.mobile' => 'nullable|string|max:32',
            'create_deal' => 'nullable|boolean',
            'deal' => 'nullable|array',
            'deal.title' => 'nullable|string|max:191',
            'deal.pipeline_id' => ['nullable','integer', Rule::exists('pipelines','id')->where('company_id',$companyId)],
            'deal.stage_id' => ['nullable','integer', Rule::exists('pipeline_stages','id')->where('company_id',$companyId)],
            'deal.amount' => 'nullable|numeric|min:0|max:9999999999999',
            'deal.currency' => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'deal.probability' => 'nullable|integer|min:0|max:100',
            'deal.expected_close_date' => 'nullable|date',
            // Legacy one-click API fields remain accepted for backward compatibility.
            'name'         => 'nullable|string|max:191',
            'type'         => 'nullable|string|in:company,individual',
            'group_id'     => ['nullable','integer', Rule::exists('customer_groups','id')->where('company_id',$companyId)],
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->leads->convert($lead, array_filter($options, fn ($v) => $v !== null));
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'customer' => new CustomerResource($result['customer']),
            'contact' => $result['contact'] ? new ContactResource($result['contact']->load('customer:id,name,customer_no')) : null,
            'deal' => $result['deal'] ? new DealResource($result['deal']) : null,
            'lead' => new LeadResource($this->leads->find($id)),
        ], 'Lead converted', 201);
    }

    public function storeAttachment(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $request->validate([
            'file' => 'required|file|max:'.self::MAX_UPLOAD_KB.'|mimetypes:'.implode(',', [
                'image/jpeg','image/png','image/gif','image/webp',
                'application/pdf','text/plain','text/csv',
                'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
        ]);

        $file = $request->file('file');
        $path = $file->store('leads/'.date('Y/m'), 'local');

        $attachment = $lead->attachments()->create([
            'company_id'  => $lead->company_id,
            'uploaded_by' => auth()->id(),
            'disk' => 'local', 'path' => $path,
            'name' => $file->getClientOriginalName(),
            // getMimeType() is guessed from content; getClientMimeType() is whatever the
            // client claimed and is spoofable. Store the value we actually validated.
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return $this->success([
            'id' => $attachment->id, 'name' => $attachment->name, 'mime' => $attachment->mime,
            'size' => $attachment->size, 'kind' => $attachment->kind, 'url' => $attachment->url,
        ], 'Attachment uploaded', 201);
    }

    public function destroyAttachment(int $id, int $attachmentId): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->attachments()->findOrFail($attachmentId)->delete();
        return $this->success(null, 'Attachment deleted');
    }

    /** Score breakdown without persisting — lets the UI explain the number. */
    public function score(int $id): JsonResponse
    {
        $lead = Lead::with('status')->findOrFail($id);
        return $this->success(app(\App\Services\LeadScoringService::class)->evaluate($lead));
    }
}
