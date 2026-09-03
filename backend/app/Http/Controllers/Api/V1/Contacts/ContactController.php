<?php

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Customer;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
