<?php
namespace App\Services;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Jobs\RunImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulk CSV import for leads / contacts / customers. Flow: createBatch (upload + auto-map) →
 * preview (validate a sample + count) → commit (queued RunImport) → process (create/update/skip
 * per row, storing error rows for a downloadable report). All company-scoped explicitly since the
 * commit job runs unauthenticated.
 */
class ImportService
{
    private const DISK = 'local';
    private const MAX_ROWS = 5000;         // safety cap per file
    private const PREVIEW_ROWS = 20;

    public function __construct(
        private readonly LeadService $leads,
        private readonly CustomerService $customers,
        private readonly ContactService $contacts,
    ) {}

    /** Importable entities and their fields (key, label, required, type, enum). */
    public function fieldsFor(string $entity): array
    {
        return match ($entity) {
            'lead' => [
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'company_name', 'label' => 'Company'],
                ['key' => 'title', 'label' => 'Job title'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'mobile', 'label' => 'Mobile'],
                ['key' => 'website', 'label' => 'Website'],
                ['key' => 'priority', 'label' => 'Priority', 'type' => 'enum', 'enum' => Lead::PRIORITIES],
                ['key' => 'estimated_value', 'label' => 'Estimated value', 'type' => 'number'],
                ['key' => 'source', 'label' => 'Source (name/code)'],
                ['key' => 'status', 'label' => 'Status (name/code)'],
            ],
            'contact' => [
                ['key' => 'account', 'label' => 'Account (customer no. or name)', 'required' => true],
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'title', 'label' => 'Job title'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'mobile', 'label' => 'Mobile'],
            ],
            'customer' => [
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'customer_no', 'label' => 'Customer no. (blank = auto)'],
                ['key' => 'type', 'label' => 'Type', 'type' => 'enum', 'enum' => ['company', 'individual']],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'mobile', 'label' => 'Mobile'],
                ['key' => 'tax_id', 'label' => 'Tax ID'],
                ['key' => 'website', 'label' => 'Website'],
                ['key' => 'group', 'label' => 'Group (name/code)'],
            ],
            default => [],
        };
    }

    public function entities(): array
    {
        return [
            ['key' => 'lead', 'label' => 'Leads'],
            ['key' => 'contact', 'label' => 'Contacts'],
            ['key' => 'customer', 'label' => 'Customers'],
        ];
    }

    /** Store the file, read its header + a small sample, and suggest a mapping by header name. */
    public function createBatch(string $entity, UploadedFile $file, int $companyId, ?int $userId): array
    {
        $path = $file->storeAs("imports/{$companyId}", Str::uuid().'.csv', self::DISK);
        $batch = ImportBatch::create([
            'company_id' => $companyId, 'user_id' => $userId, 'entity' => $entity,
            'filename' => $file->getClientOriginalName(), 'path' => $path, 'status' => 'uploaded',
        ]);

        [$headers, $sample] = $this->readCsv($path, self::PREVIEW_ROWS);
        $fields = $this->fieldsFor($entity);
        $suggested = $this->suggestMapping($headers, $fields);

        return [
            'batch' => $this->present($batch),
            'columns' => $headers,
            'sample' => $sample,
            'fields' => $fields,
            'suggested_mapping' => $suggested,
        ];
    }

    /** Validate a sample of rows against the mapping and count valid/error/dupe across the whole file. */
    public function preview(ImportBatch $batch, array $mapping): array
    {
        $required = collect($this->fieldsFor($batch->entity))->where('required', true)->pluck('key')->all();
        $missing = array_diff($required, array_values($mapping));
        if ($missing) {
            return ['ok' => false, 'message' => 'Map all required fields: '.implode(', ', $missing)];
        }

        [$headers, $rows] = $this->readCsv($batch->path, self::MAX_ROWS);
        $total = count($rows);
        $valid = 0; $errors = 0; $preview = [];
        foreach ($rows as $i => $raw) {
            $mapped = $this->mapRow($raw, $mapping);
            $rowErrors = $this->validateRow($batch, $mapped);
            $isValid = empty($rowErrors);
            $isValid ? $valid++ : $errors++;
            if ($i < self::PREVIEW_ROWS) {
                $preview[] = ['row' => $i + 2, 'data' => $mapped, 'errors' => $rowErrors];
            }
        }
        return ['ok' => true, 'total' => $total, 'valid' => $valid, 'errors' => $errors, 'preview' => $preview];
    }

    /** Persist the mapping + dedupe choice and queue the commit. */
    public function commit(ImportBatch $batch, array $mapping, string $dedupe): ImportBatch
    {
        $batch->update([
            'mapping' => $mapping,
            'dedupe' => in_array($dedupe, ['skip', 'update'], true) ? $dedupe : 'skip',
            'status' => 'processing',
            'created_rows' => 0, 'updated_rows' => 0, 'skipped_rows' => 0, 'error_rows' => 0,
        ]);
        $batch->rows()->delete();
        RunImport::dispatch($batch->id);
        return $batch->refresh();
    }

    /** Full commit — runs in the queue worker. */
    public function process(ImportBatch $batch): void
    {
        try {
            // Act as the importing user so the entity services' auth-scoped logic (lead_no/customer_no
            // sequencing, auto-assign, timeline author, owner defaults, company scope) works in the
            // unauthenticated worker. Without this, sequence generators collide across tenants.
            if ($batch->user_id && ($actor = \App\Models\User::withoutGlobalScopes()->find($batch->user_id))) {
                auth()->setUser($actor);
            }

            [$headers, $rows] = $this->readCsv($batch->path, self::MAX_ROWS);
            $mapping = $batch->mapping ?? [];
            $created = $updated = $skipped = $errored = 0;

            foreach ($rows as $i => $raw) {
                $mapped = $this->mapRow($raw, $mapping);
                $rowErrors = $this->validateRow($batch, $mapped);
                if ($rowErrors) {
                    $errored++;
                    ImportRow::create(['batch_id' => $batch->id, 'company_id' => $batch->company_id,
                        'row_number' => $i + 2, 'data' => $mapped, 'errors' => $rowErrors]);
                    continue;
                }
                try {
                    $outcome = $this->upsert($batch, $mapped);
                    $outcome === 'updated' ? $updated++ : ($outcome === 'skipped' ? $skipped++ : $created++);
                } catch (\Throwable $e) {
                    $errored++;
                    ImportRow::create(['batch_id' => $batch->id, 'company_id' => $batch->company_id,
                        'row_number' => $i + 2, 'data' => $mapped, 'errors' => ['Import failed: '.$e->getMessage()]]);
                }
            }

            $batch->update([
                'status' => 'completed', 'total_rows' => count($rows),
                'created_rows' => $created, 'updated_rows' => $updated,
                'skipped_rows' => $skipped, 'error_rows' => $errored,
            ]);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    // ---- internals ---------------------------------------------------------

    private function upsert(ImportBatch $batch, array $m): string
    {
        $companyId = $batch->company_id;
        return match ($batch->entity) {
            'lead' => $this->upsertLead($batch, $m, $companyId),
            'customer' => $this->upsertCustomer($batch, $m, $companyId),
            'contact' => $this->upsertContact($batch, $m, $companyId),
            default => 'skipped',
        };
    }

    private function upsertLead(ImportBatch $batch, array $m, int $companyId): string
    {
        $existing = $this->matchLead($companyId, $m);
        $attrs = array_filter([
            'company_name' => $m['company_name'] ?? null, 'title' => $m['title'] ?? null,
            'email' => $m['email'] ?? null, 'phone' => $m['phone'] ?? null, 'mobile' => $m['mobile'] ?? null,
            'website' => $m['website'] ?? null, 'priority' => $m['priority'] ?? null,
            'estimated_value' => isset($m['estimated_value']) ? (float) $m['estimated_value'] : null,
            'source_id' => $this->resolveId(LeadSource::class, $companyId, $m['source'] ?? null),
            'status_id' => $this->resolveId(LeadStatus::class, $companyId, $m['status'] ?? null),
        ], fn ($v) => $v !== null);

        if ($existing) {
            if ($batch->dedupe !== 'update') return 'skipped';
            $existing->update($attrs);
            return 'updated';
        }
        $this->leads->create(array_merge($attrs, [
            'company_id' => $companyId, 'name' => $m['name'], 'owner_id' => $batch->user_id,
        ]));
        return 'created';
    }

    private function upsertCustomer(ImportBatch $batch, array $m, int $companyId): string
    {
        $existing = $this->matchCustomer($companyId, $m);
        $attrs = array_filter([
            'type' => $m['type'] ?? null, 'email' => $m['email'] ?? null, 'phone' => $m['phone'] ?? null,
            'mobile' => $m['mobile'] ?? null, 'tax_id' => $m['tax_id'] ?? null, 'website' => $m['website'] ?? null,
            'group_id' => $this->resolveId(CustomerGroup::class, $companyId, $m['group'] ?? null),
        ], fn ($v) => $v !== null);

        if ($existing) {
            if ($batch->dedupe !== 'update') return 'skipped';
            $existing->update($attrs);
            return 'updated';
        }
        $this->customers->create(array_merge($attrs, [
            'company_id' => $companyId, 'name' => $m['name'], 'status' => 'active',
            'type' => $m['type'] ?? 'company', 'owner_id' => $batch->user_id,
            'customer_no' => $m['customer_no'] ?? null,
        ]));
        return 'created';
    }

    private function upsertContact(ImportBatch $batch, array $m, int $companyId): string
    {
        $customerId = $this->resolveAccount($companyId, $m['account'] ?? null);
        // validateRow already guaranteed the account resolves.
        $existing = Contact::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where(function ($q) use ($m) {
                if (!empty($m['email'])) $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($m['email'])]);
                if (!empty($m['phone'])) $q->orWhere('phone', $m['phone'])->orWhere('mobile', $m['phone']);
            })->first();

        $attrs = array_filter([
            'title' => $m['title'] ?? null, 'department' => $m['department'] ?? null,
            'email' => $m['email'] ?? null, 'phone' => $m['phone'] ?? null, 'mobile' => $m['mobile'] ?? null,
        ], fn ($v) => $v !== null);

        if ($existing) {
            if ($batch->dedupe !== 'update') return 'skipped';
            $existing->update($attrs);
            return 'updated';
        }
        $this->contacts->create(array_merge($attrs, [
            'company_id' => $companyId, 'customer_id' => $customerId, 'name' => $m['name'],
        ]));
        return 'created';
    }

    private function matchLead(int $companyId, array $m): ?Lead
    {
        $email = trim((string) ($m['email'] ?? '')); $phone = trim((string) ($m['phone'] ?? ''));
        if ($email === '' && $phone === '') return null;
        return Lead::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(function ($q) use ($email, $phone) {
                if ($email !== '') $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                if ($phone !== '') $q->orWhere('phone', $phone)->orWhere('mobile', $phone);
            })->first();
    }

    private function matchCustomer(int $companyId, array $m): ?Customer
    {
        $email = trim((string) ($m['email'] ?? '')); $tax = trim((string) ($m['tax_id'] ?? ''));
        $no = trim((string) ($m['customer_no'] ?? ''));
        if ($email === '' && $tax === '' && $no === '') return null;
        return Customer::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(function ($q) use ($email, $tax, $no) {
                if ($email !== '') $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                if ($tax !== '') $q->orWhere('tax_id', $tax);
                if ($no !== '') $q->orWhere('customer_no', $no);
            })->first();
    }

    private function resolveAccount(int $companyId, ?string $val): ?int
    {
        $val = trim((string) $val);
        if ($val === '') return null;
        return Customer::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(fn ($q) => $q->where('customer_no', $val)->orWhere('name', $val))
            ->value('id');
    }

    private function resolveId(string $model, int $companyId, ?string $val): ?int
    {
        $val = trim((string) $val);
        if ($val === '') return null;
        return $model::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(fn ($q) => $q->where('code', $val)->orWhere('name', $val))
            ->value('id');
    }

    private function validateRow(ImportBatch $batch, array $m): array
    {
        $errors = [];
        foreach ($this->fieldsFor($batch->entity) as $f) {
            $key = $f['key']; $val = trim((string) ($m[$key] ?? ''));
            if (($f['required'] ?? false) && $val === '') { $errors[] = "{$f['label']} is required"; continue; }
            if ($val === '') continue;
            $type = $f['type'] ?? 'string';
            if ($type === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email: {$val}";
            if ($type === 'number' && !is_numeric($val)) $errors[] = "{$f['label']} must be a number";
            if ($type === 'enum' && !in_array(mb_strtolower($val), array_map('mb_strtolower', $f['enum']), true))
                $errors[] = "{$f['label']} must be one of: ".implode(', ', $f['enum']);
        }
        // Cross-field: a contact's account must resolve to a real customer.
        if ($batch->entity === 'contact' && !empty($m['account']) && !$this->resolveAccount($batch->company_id, $m['account'])) {
            $errors[] = "Unknown account: {$m['account']}";
        }
        return $errors;
    }

    private function mapRow(array $raw, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $header => $field) {
            if ($field === '' || $field === null) continue;
            $out[$field] = isset($raw[$header]) ? trim((string) $raw[$header]) : '';
        }
        return $out;
    }

    private function suggestMapping(array $headers, array $fields): array
    {
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $s));
        $byKey = [];
        foreach ($fields as $f) { $byKey[$norm($f['key'])] = $f['key']; $byKey[$norm($f['label'])] = $f['key']; }
        $map = [];
        foreach ($headers as $h) {
            $n = $norm($h);
            $map[$h] = $byKey[$n] ?? '';
        }
        return $map;
    }

    /** @return array{0:array<int,string>,1:array<int,array<string,string>>} [headers, rows] */
    private function readCsv(string $path, int $limit): array
    {
        $full = Storage::disk(self::DISK)->path($path);
        if (!is_file($full)) return [[], []];
        $headers = []; $rows = [];
        if (($fh = fopen($full, 'r')) !== false) {
            $headers = fgetcsv($fh) ?: [];
            $headers = array_map(fn ($h) => trim((string) $h), $headers);
            $n = 0;
            while (($line = fgetcsv($fh)) !== false && $n < $limit) {
                $row = [];
                foreach ($headers as $idx => $h) { $row[$h] = $line[$idx] ?? ''; }
                $rows[] = $row; $n++;
            }
            fclose($fh);
        }
        return [$headers, $rows];
    }

    public function present(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id, 'entity' => $batch->entity, 'filename' => $batch->filename,
            'status' => $batch->status, 'dedupe' => $batch->dedupe,
            'total_rows' => $batch->total_rows, 'created_rows' => $batch->created_rows,
            'updated_rows' => $batch->updated_rows, 'skipped_rows' => $batch->skipped_rows,
            'error_rows' => $batch->error_rows, 'error' => $batch->error,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }
}
