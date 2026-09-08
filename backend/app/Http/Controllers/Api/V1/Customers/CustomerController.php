<?php
namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\CustomerResource;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'   => 'nullable|string|in:all,active,on_hold,blocked,archived',
            'type'     => 'nullable|string|in:company,individual',
            'group_id' => 'nullable|integer',
            'owner_id' => 'nullable|string',
            'q'        => 'nullable|string|max:191',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated(
            $this->customers->paginate($filters, (int) ($filters['per_page'] ?? 25)),
            CustomerResource::class
        );
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->customers->stats());
    }

    /** Lookup data the create/edit form needs, in one round trip. */
    public function meta(): JsonResponse
    {
        return $this->success([
            'groups' => CustomerGroup::where('is_active', true)->orderBy('name')->get(['id','name','code','discount_percent','payment_terms_days']),
            'price_books' => \App\Models\PriceBook::active()->orderBy('name')->get(['id','name','currency']),
            'types' => Customer::TYPES,
            'statuses' => Customer::STATUSES,
            'custom_fields' => app(\App\Services\CustomFieldService::class)->definitions('customer'),
            'campaigns' => \App\Models\Campaign::orderByDesc('id')->limit(100)->get(['id', 'name']),
            'campaign_member_statuses' => Customer::CAMPAIGN_MEMBER_STATUSES,
            'next_customer_no' => $this->customers->nextCustomerNo(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new CustomerResource($this->customers->find($id)));
    }

    /** Unified Customer-360 timeline: the customer's own events + its contacts' + its deals'. */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $customer = $this->customers->find($id); // company-scoped; 404 if not in tenant
        $opts = $request->validate([
            'type'     => 'nullable|string|max:32',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->success($this->customers->timeline($customer, $opts));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->validated());
        return $this->success(new CustomerResource($customer), 'Customer created', 201);
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        return $this->success(
            new CustomerResource($this->customers->update($customer, $request->validated())),
            'Customer updated'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        Customer::findOrFail($id)->delete();   // soft delete
        return $this->success(null, 'Customer deleted');
    }

    /** Campaign memberships (marketing lists) this account belongs to. */
    public function campaignMemberships(int $id): JsonResponse
    {
        $customer = Customer::with(['campaigns' => fn ($q) => $q->orderByDesc('campaign_customer.id')])->findOrFail($id);
        return $this->success($customer->campaigns->map(fn ($c) => [
            'campaign_id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'campaign_status' => $c->status,
            'status' => $c->pivot->status, 'added_at' => optional($c->pivot->added_at)->toIso8601String(),
        ])->values());
    }

    /** Add this account to a campaign, or update its member status if already a member. */
    public function attachCampaign(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'campaign_id' => ['required', 'integer',
                Rule::exists('campaigns', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(Customer::CAMPAIGN_MEMBER_STATUSES)],
        ]);
        $status = $data['status'] ?? 'member';

        if ($customer->campaigns()->where('campaigns.id', $data['campaign_id'])->exists()) {
            $customer->campaigns()->updateExistingPivot($data['campaign_id'], ['status' => $status]);
        } else {
            // company_id is set explicitly: pivot rows aren't models, so BelongsToCompany can't fill it.
            $customer->campaigns()->attach($data['campaign_id'], [
                'company_id' => $customer->company_id, 'status' => $status, 'added_at' => now(),
            ]);
            $name = \App\Models\Campaign::whereKey($data['campaign_id'])->value('name');
            \App\Models\TimelineActivity::record($customer, 'system', 'Added to campaign '.$name);
        }
        return $this->campaignMemberships($id);
    }

    /** Remove this account from a campaign. */
    public function detachCampaign(int $id, int $campaignId): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        if ($customer->campaigns()->where('campaigns.id', $campaignId)->exists()) {
            $name = \App\Models\Campaign::whereKey($campaignId)->value('name');
            $customer->campaigns()->detach($campaignId);
            \App\Models\TimelineActivity::record($customer, 'system', 'Removed from campaign '.$name);
        }
        return $this->campaignMemberships($id);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|string|in:note,call,email,meeting',
        ]);
        $customer = Customer::findOrFail($id);
        $entry = $this->customers->addNote($customer, $data['body'], $data['type'] ?? 'note');
        return $this->success([
            'id' => $entry->id, 'type' => $entry->type, 'title' => $entry->title,
            'body' => $entry->body, 'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'occurred_human' => $entry->occurred_at?->diffForHumans(),
        ], 'Note added', 201);
    }

    /* ------------------------------------------------------------- contacts */

    public function storeContact(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:191',
            'title'      => 'nullable|string|max:128',
            'email'      => 'nullable|email|max:191',
            'phone'      => 'nullable|string|max:32',
            'mobile'     => 'nullable|string|max:32',
            'is_primary' => 'nullable|boolean',
            'notes'      => 'nullable|string|max:2000',
        ]);

        $contact = $customer->contacts()->create($data + ['company_id' => $customer->company_id]);
        if (!empty($data['is_primary'])) $this->demoteOtherPrimaries($customer, $contact->id);

        return $this->success(new ContactResource($contact), 'Contact added', 201);
    }

    public function updateContact(Request $request, int $id, int $contactId): JsonResponse
    {
        $contact = Contact::where('customer_id', $id)->findOrFail($contactId);
        $data = $request->validate([
            'name'       => 'sometimes|string|max:191',
            'title'      => 'nullable|string|max:128',
            'email'      => 'nullable|email|max:191',
            'phone'      => 'nullable|string|max:32',
            'mobile'     => 'nullable|string|max:32',
            'is_primary' => 'nullable|boolean',
            'notes'      => 'nullable|string|max:2000',
        ]);
        $contact->update($data);
        if (!empty($data['is_primary'])) $this->demoteOtherPrimaries($contact->customer, $contact->id);

        return $this->success(new ContactResource($contact->fresh()), 'Contact updated');
    }

    public function destroyContact(int $id, int $contactId): JsonResponse
    {
        Contact::where('customer_id', $id)->findOrFail($contactId)->delete();
        return $this->success(null, 'Contact deleted');
    }

    /**
     * Grants/revokes customer-portal login for this contact. Login is looked up by email alone
     * (no tenant selector on the portal login screen), so a contact's email must be unique among
     * portal-enabled contacts across the whole instance, not just this company — enforced here,
     * not at the DB level (MySQL has no filtered/partial unique index).
     */
    public function updateContactPortal(Request $request, int $id, int $contactId): JsonResponse
    {
        $contact = Contact::where('customer_id', $id)->findOrFail($contactId);
        $data = $request->validate([
            'portal_enabled' => 'required|boolean',
            'password'       => 'nullable|string|min:8',
        ]);

        if (!empty($data['portal_enabled'])) {
            if (!$contact->email) {
                return $this->error('Contact needs an email address before enabling portal access', 422);
            }
            $emailTaken = Contact::withoutGlobalScope('company')
                ->where('email', $contact->email)->where('portal_enabled', true)
                ->where('id', '!=', $contact->id)->exists();
            if ($emailTaken) {
                return $this->error('Another portal-enabled contact already uses this email', 422);
            }
            if (empty($data['password']) && !$contact->password) {
                return $this->error('A password is required to enable portal access', 422);
            }
        }

        $patch = ['portal_enabled' => (bool) $data['portal_enabled']];
        if (!empty($data['password'])) $patch['password'] = $data['password'];
        $contact->forceFill($patch)->save();

        return $this->success(new ContactResource($contact->fresh()), 'Portal access updated');
    }

    /** Exactly one primary contact per customer. */
    private function demoteOtherPrimaries(Customer $customer, int $keepId): void
    {
        $customer->contacts()->where('id', '!=', $keepId)->update(['is_primary' => false]);
    }
}
