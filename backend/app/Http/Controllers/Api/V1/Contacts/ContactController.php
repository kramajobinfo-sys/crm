<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Customer;
use App\Models\TimelineActivity;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contacts) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'customer_id' => ['nullable', 'integer'],
            'primary' => ['nullable', 'in:all,yes'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->paginated(
            $this->contacts->paginate($filters, (int) ($filters['per_page'] ?? 25)),
            ContactResource::class,
        );
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'accounts' => Customer::where('status', '!=', 'archived')
                ->orderBy('name')->limit(500)->get(['id', 'customer_no', 'name', 'type', 'status']),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new ContactResource($this->contacts->find($id)));
    }

    /** Current per-channel consent state + full opt-in/opt-out history. */
    public function consents(int $id): JsonResponse
    {
        $contact = $this->contacts->find($id); // company-scoped; 404 if not in tenant
        return $this->success($this->consentPayload($contact));
    }

    /** Record a consent grant or withdrawal for one channel (append-only history). */
    public function storeConsent(Request $request, int $id): JsonResponse
    {
        $contact = $this->contacts->find($id); // company-scoped; 404 if not in tenant
        $data = $request->validate([
            'channel' => ['required', Rule::in(ContactConsent::CHANNELS)],
            'status'  => ['required', Rule::in(['granted', 'withdrawn'])],
            'source'  => ['nullable', 'string', 'max:64'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $contact->consents()->create([
            'contact_id'  => $contact->id,
            'channel'     => $data['channel'],
            'status'      => $data['status'],
            'source'      => $data['source'] ?? 'agent',
            'note'        => $data['note'] ?? null,
            'user_id'     => $request->user()->id,
            'occurred_at' => now(),
        ]);
        $contact->unsetRelation('consents');

        $verb = $data['status'] === 'granted' ? 'opted in to' : 'opted out of';
        TimelineActivity::record(
            $contact, 'system', 'Contact '.$verb.' '.$data['channel'], $data['note'] ?? null,
            ['channel' => $data['channel'], 'status' => $data['status']]
        );

        return $this->success($this->consentPayload($contact), 'Consent updated');
    }

    /** Build the { current[], history[] } consent view for a contact. */
    private function consentPayload(Contact $contact): array
    {
        $history = $contact->consents()->with('user:id,name')->get()->map(fn (ContactConsent $c) => [
            'id'          => $c->id,
            'channel'     => $c->channel,
            'status'      => $c->status,
            'source'      => $c->source,
            'note'        => $c->note,
            'user'        => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
            'occurred_at' => optional($c->occurred_at)->toIso8601String(),
        ])->values();

        $current = collect(ContactConsent::CHANNELS)->map(function (string $ch) use ($contact) {
            $latest = $contact->consentFor($ch);
            return [
                'channel'     => $ch,
                'status'      => $latest?->status ?? 'unset',
                'can_receive' => $contact->canReceive($ch),
                'opt_in_only' => in_array($ch, ContactConsent::OPT_IN_CHANNELS, true),
                'occurred_at' => optional($latest?->occurred_at)->toIso8601String(),
                'source'      => $latest?->source,
            ];
        })->values();

        return ['current' => $current, 'history' => $history];
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        return $this->success(
            new ContactResource($this->contacts->create($request->validated())),
            'Contact created',
            201,
        );
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        return $this->success(
            new ContactResource($this->contacts->update($contact, $request->validated())),
            'Contact updated',
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->contacts->delete(Contact::findOrFail($id));
        return $this->success(null, 'Contact deleted');
    }
}
