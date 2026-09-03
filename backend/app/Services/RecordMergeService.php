<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordMergeService
{
    private const MODELS = ['lead' => Lead::class, 'account' => Customer::class, 'contact' => Contact::class];

    private const FIELDS = [
        'lead' => ['name','company_name','title','email','phone','mobile','website','source_id','status_id','rating','owner_id','branch_id','estimated_value','currency','expected_close_date','notes'],
        'account' => ['name','legal_name','email','phone','mobile','website','tax_id','group_id','owner_id','branch_id','currency','price_book_id','credit_limit','payment_terms_days','notes'],
        'contact' => ['name','title','email','phone','mobile','notes'],
    ];

    public function preview(string $type, int $primaryId, int $duplicateId): array
    {
        [$primary, $duplicate] = $this->records($type, $primaryId, $duplicateId);
        $fields = collect(self::FIELDS[$type])->map(function (string $field) use ($primary, $duplicate) {
            $left = $primary->getAttribute($field);
            $right = $duplicate->getAttribute($field);
            return ['field' => $field, 'primary' => $left, 'duplicate' => $right,
                'conflict' => $this->present($left) && $this->present($right) && (string) $left !== (string) $right,
                'recommended' => !$this->present($left) && $this->present($right) ? 'duplicate' : 'primary'];
        })->values()->all();

        return ['type' => $type, 'primary' => $this->summary($primary), 'duplicate' => $this->summary($duplicate),
            'fields' => $fields, 'relationships' => $this->relationshipCounts($type, $duplicate)];
    }

    public function merge(string $type, int $primaryId, int $duplicateId, array $fieldSources): array
    {
        return DB::transaction(function () use ($type, $primaryId, $duplicateId, $fieldSources) {
            [$primary, $duplicate] = $this->records($type, $primaryId, $duplicateId, true);
            $selected = [];
            foreach (self::FIELDS[$type] as $field) {
                $source = ($fieldSources[$field] ?? null) === 'duplicate' ? 'duplicate' : 'primary';
                if (!$this->present($primary->getAttribute($field)) && $this->present($duplicate->getAttribute($field))) $source = 'duplicate';
                if ($source === 'duplicate') $primary->setAttribute($field, $duplicate->getAttribute($field));
                $selected[$field] = $source;
            }
            if ($type === 'lead' && !$primary->converted_to_customer_id && $duplicate->converted_to_customer_id) {
                $primary->converted_to_customer_id = $duplicate->converted_to_customer_id;
                $primary->converted_at = $duplicate->converted_at;
            }
            if ($type === 'contact' && $duplicate->portal_enabled) {
                $primary->portal_enabled = true;
                if (!$primary->password) $primary->password = $duplicate->password;
                if (!$primary->last_login_at) $primary->last_login_at = $duplicate->last_login_at;
            }
            $primary->save();
            $moved = $this->moveRelationships($type, $primary, $duplicate);
            $duplicateSnapshot = $this->summary($duplicate);
            $duplicate->delete();

            AuditLog::create([
                'company_id' => $primary->company_id, 'user_id' => auth()->id(),
                'auditable_type' => $primary::class, 'auditable_id' => $primary->id, 'event' => 'merged',
                'old_values' => ['duplicate' => $duplicateSnapshot],
                'new_values' => ['primary_id' => $primary->id, 'field_sources' => $selected, 'relationships_moved' => $moved],
                'url' => request()->fullUrl(), 'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 500), 'created_at' => now(),
            ]);

            return ['record' => $this->summary($primary->fresh()), 'relationships_moved' => $moved];
        }, 3);
    }

    private function records(string $type, int $primaryId, int $duplicateId, bool $lock = false): array
    {
        if ($primaryId === $duplicateId || !isset(self::MODELS[$type])) {
            throw ValidationException::withMessages(['duplicate_id' => 'Choose two different records.']);
        }
        $model = self::MODELS[$type];
        $find = fn (int $id) => $model::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        $primary = $find($primaryId);
        $duplicate = $find($duplicateId);
        if ((int) $primary->company_id !== (int) $duplicate->company_id) {
            throw ValidationException::withMessages(['duplicate_id' => 'Records from different tenants cannot be merged.']);
        }
        if ($type === 'contact' && (int) $primary->customer_id !== (int) $duplicate->customer_id) {
            throw ValidationException::withMessages(['duplicate_id' => 'Contacts must belong to the same Account before merging.']);
        }
        if ($type === 'lead' && $primary->converted_to_customer_id && $duplicate->converted_to_customer_id
            && (int) $primary->converted_to_customer_id !== (int) $duplicate->converted_to_customer_id) {
            throw ValidationException::withMessages(['duplicate_id' => 'Leads converted to different Accounts cannot be merged.']);
        }
        return [$primary, $duplicate];
    }

    private function moveRelationships(string $type, Model $primary, Model $duplicate): array
    {
        $companyId = $primary->company_id;
        $moved = [];
        $update = function (string $table, string $column) use ($primary, $duplicate, $companyId, &$moved) {
            $moved[$table] = DB::table($table)->where('company_id', $companyId)->where($column, $duplicate->id)->update([$column => $primary->id]);
        };
        if ($type === 'lead') {
            $update('deals', 'lead_id'); $update('web_visitors', 'lead_id');
        } elseif ($type === 'account') {
            if (DB::table('contacts')->where('company_id', $companyId)->where('customer_id', $primary->id)->where('is_primary', true)->exists()) {
                DB::table('contacts')->where('company_id', $companyId)->where('customer_id', $duplicate->id)->update(['is_primary' => false]);
            }
            foreach (['contacts','deals','tickets','quotations','sales_orders','invoices','payments','customer_credits','web_visitors'] as $table) $update($table, 'customer_id');
            DB::table('leads')->where('company_id', $companyId)->where('converted_to_customer_id', $duplicate->id)->update(['converted_to_customer_id' => $primary->id]);
        } else {
            $pivots = DB::table('deal_contact')->where('company_id', $companyId)->where('contact_id', $duplicate->id)->get();
            $count = 0;
            foreach ($pivots as $pivot) {
                $existing = DB::table('deal_contact')->where('deal_id', $pivot->deal_id)->where('contact_id', $primary->id)->first();
                if ($pivot->is_primary) {
                    DB::table('deal_contact')->where('deal_id', $pivot->deal_id)->update(['is_primary' => false]);
                }
                if (!$existing) {
                    DB::table('deal_contact')->insert(['company_id' => $companyId, 'deal_id' => $pivot->deal_id,
                        'contact_id' => $primary->id, 'role' => $pivot->role, 'is_primary' => $pivot->is_primary,
                        'created_at' => $pivot->created_at, 'updated_at' => now()]);
                    $count++;
                } elseif ($pivot->is_primary) {
                    DB::table('deal_contact')->where('id', $existing->id)->update(['is_primary' => true, 'updated_at' => now()]);
                }
            }
            DB::table('deal_contact')->where('company_id', $companyId)->where('contact_id', $duplicate->id)->delete();
            $moved['deal_contact'] = $count;
        }
        foreach ([['addresses','addressable'], ['timeline_activities','subject'], ['attachments','attachable']] as [$table, $morph]) {
            $moved[$table] = DB::table($table)->where('company_id', $companyId)->where($morph.'_type', $primary::class)
                ->where($morph.'_id', $duplicate->id)->update([$morph.'_id' => $primary->id]);
        }
        return $moved;
    }

    private function relationshipCounts(string $type, Model $record): array
    {
        $counts = []; $companyId = $record->company_id;
        $count = function (string $table, string $column) use ($record, $companyId, &$counts) {
            $counts[$table] = DB::table($table)->where('company_id', $companyId)->where($column, $record->id)->count();
        };
        if ($type === 'lead') foreach ([['deals','lead_id'],['web_visitors','lead_id']] as [$t,$c]) $count($t,$c);
        if ($type === 'account') foreach (['contacts','deals','tickets','quotations','sales_orders','invoices','payments','customer_credits','web_visitors'] as $t) $count($t,'customer_id');
        if ($type === 'contact') $count('deal_contact','contact_id');
        foreach ([['addresses','addressable'],['timeline_activities','subject'],['attachments','attachable']] as [$table,$morph]) {
            $counts[$table] = DB::table($table)->where('company_id', $companyId)->where($morph.'_type', $record::class)->where($morph.'_id', $record->id)->count();
        }
        return array_filter($counts);
    }

    private function summary(Model $record): array
    {
        return ['id' => $record->id, 'label' => $record->name, 'number' => $record->lead_no ?? $record->customer_no ?? null];
    }

    private function present(mixed $value): bool { return $value !== null && $value !== ''; }
}
