<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContactService
{
    /**
     * A single chronological feed for one contact: entries logged directly on the contact plus
     * everything on the opportunities the contact is a party to (deal_contact). Company-scoped.
     */
    public function timeline(Contact $contact, array $opts = []): LengthAwarePaginator
    {
        $perPage = (int) ($opts['per_page'] ?? 30);
        $dealIds = $contact->deals()->pluck('deals.id')->all();
        $subjects = [Contact::class => [$contact->id], Deal::class => $dealIds];

        $page = TimelineActivity::query()
            ->where('company_id', $contact->company_id)
            ->where(function ($w) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    if (!empty($ids)) $w->orWhere(fn ($x) => $x->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            })
            ->when(!empty($opts['type']), fn ($q) => $q->where('type', $opts['type']))
            ->with(['user:id,name', 'subject'])
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($perPage);

        $page->getCollection()->transform(fn (TimelineActivity $t) => [
            'id' => $t->id, 'type' => $t->type, 'title' => $t->title, 'body' => $t->body, 'meta' => $t->meta,
            'occurred_at' => optional($t->occurred_at)->toIso8601String(),
            'occurred_human' => optional($t->occurred_at)->diffForHumans(),
            'user' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
            'source' => ['type' => class_basename($t->subject_type), 'id' => $t->subject_id],
        ]);
        return $page;
    }

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
            if (array_key_exists('custom_fields', $data)) {
                $data['custom_fields'] = app(CustomFieldService::class)->sanitize('contact', (array) $data['custom_fields']);
            }
            $contact = Contact::create($data);
            if ($contact->is_primary) $this->demoteOtherPrimaries($contact);
            return $this->find($contact->id);
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data) {
            if (array_key_exists('custom_fields', $data)) {
                // Merge onto existing values so a partial update doesn't wipe untouched fields.
                $data['custom_fields'] = array_merge($contact->custom_fields ?? [],
                    app(CustomFieldService::class)->sanitize('contact', (array) $data['custom_fields']));
            }
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
