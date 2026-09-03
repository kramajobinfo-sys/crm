<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContactService
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Contact::query()
            ->with('customer:id,customer_no,name,type,status')
            ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(($filters['primary'] ?? null) === 'yes', fn ($q) => $q->where('is_primary', true))
            ->when(!empty($filters['q']), function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhereHas('customer', fn ($account) => $account->where('name', 'like', $like)));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): Contact
    {
        return Contact::with([
            'customer:id,customer_no,name,type,status,email,phone',
            'addresses',
        ])->findOrFail($id);
    }

    public function create(array $data): Contact
    {
        return DB::transaction(function () use ($data) {
            $contact = Contact::create($data);
            if ($contact->is_primary) $this->demoteOtherPrimaries($contact);
            return $this->find($contact->id);
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data) {
            $contact->update($data);
            if ($contact->is_primary) $this->demoteOtherPrimaries($contact);
            return $this->find($contact->id);
        });
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    private function demoteOtherPrimaries(Contact $contact): void
    {
        Contact::where('customer_id', $contact->customer_id)
            ->where('id', '!=', $contact->id)
            ->update(['is_primary' => false]);
    }
}
