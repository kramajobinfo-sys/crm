<?php
namespace App\Services\Dynamics\Syncers;

use App\Models\BcEntityMapping;
use App\Models\BcRecordLink;
use App\Models\BcSyncIssue;
use App\Models\BcSyncRun;
use App\Services\Dynamics\BusinessCentralClient;
use App\Services\Dynamics\Contracts\EntitySyncer;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared machinery for every BC -> CRM pull: paging, field mapping, business-key matching,
 * the crosswalk, idempotency and the incremental cursor. Subclasses describe *what* an
 * entity maps to; this describes *how* a pull behaves.
 *
 * Pull only, by decision — see docs/DYNAMICS_BC_SYNC_SCOPE.md. Push writes into a customer's
 * live ERP and there is no tenant available to verify it against.
 *
 * Runs UNAUTHENTICATED: RunBcSync executes on the `integrations` queue, so nothing here may
 * touch auth(). Every write supplies company_id explicitly (which also satisfies
 * BelongsToCompany's `creating` hook) and goes through the model rather than the service
 * layer — CustomerService/ProductService derive their business key from
 * auth()->user()?->company_id, which is null here and would number every pulled record
 * identically. BC owns the number anyway.
 */
abstract class PullSyncer implements EntitySyncer
{
    public function supportedDirections(): array { return ['pull']; }

    /** crm_field => bc_field, overridable per mapping via bc_entity_mappings.field_map. */
    abstract protected function defaultFieldMap(): array;

    /** The CRM column BC's `number` is matched against, e.g. customer_no / sku. */
    abstract protected function businessKey(): string;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Translate one BC row into CRM column values (company_id and the business key included). */
    abstract protected function toAttributes(array $row, array $map, int $companyId, string $key): array;

    public function sync(BusinessCentralClient $client, BcEntityMapping $mapping, BcSyncRun $run): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $companyId = (int) $mapping->company_id;

        // Mapping overrides win, but only for CRM fields this syncer actually knows about —
        // an unknown key in field_map is ignored rather than written blindly.
        $map = $this->defaultFieldMap();
        foreach ((array) ($mapping->field_map ?? []) as $crmField => $bcField) {
            if (array_key_exists($crmField, $map) && is_string($bcField) && $bcField !== '') {
                $map[$crmField] = $bcField;
            }
        }

        $rows = $client->getAllPages($client->entityUrl($this->bcEntity()), $this->query($mapping));

        $maxSeen = null;
        foreach ($rows as $row) {
            $maxSeen = $this->trackCursor($maxSeen, $row);

            $bcId = $row['id'] ?? null;
            $key  = $this->stringOrNull($row[$map[$this->businessKey()] ?? 'number'] ?? null);

            // No id means no crosswalk is possible; no business key means no safe match.
            // Both are skips with a warning, never a silent drop.
            if (!$bcId || $key === null) {
                $counts['skipped']++;
                $this->issue($run, 'map', 'warning', sprintf(
                    '%s row skipped: missing %s.', $this->bcEntity(), !$bcId ? 'id' : 'number'
                ));
                continue;
            }

            $link = BcRecordLink::withoutGlobalScopes()->firstOrNew([
                'connection_id' => $mapping->connection_id,
                'bc_entity'     => $this->bcEntity(),
                'bc_id'         => $bcId,
            ]);

            $attrs = $this->toAttributes($row, $map, $companyId, $key);
            $hash  = hash('sha256', json_encode($attrs));

            // Unchanged since the last pull — the whole point of payload_hash.
            if ($link->exists && $link->payload_hash === $hash) {
                $counts['skipped']++;
                continue;
            }

            $outcome = $this->applyRow($link, $attrs, $companyId, $key, $run, $mapping, $hash, $row);
            $counts[$outcome]++;
        }

        // Only advance on a clean run: past a partial one, the rows that failed would never be
        // offered again. And to the newest value actually SEEN, never now() — the clock would
        // skip anything modified while this run was in flight.
        if ($counts['failed'] === 0 && $maxSeen) {
            $mapping->forceFill(['sync_cursor' => $maxSeen])->save();
        }

        return $counts;
    }

    /**
     * Resolve the CRM row and write it. Returns the counter key to bump.
     *
     * Match order: crosswalk -> business key -> create. Never by name: names are not unique
     * and a wrong match silently merges two accounts.
     */
    protected function applyRow(
        BcRecordLink $link, array $attrs, int $companyId, string $key,
        BcSyncRun $run, BcEntityMapping $mapping, string $hash, array $row
    ): string {
        $class = $this->modelClass();

        $model = null;
        if ($link->exists && $link->crm_id) {
            $model = $class::withoutGlobalScope('company')->find($link->crm_id);
        }
        if (!$model) {
            $model = $class::withoutGlobalScope('company')
                ->where('company_id', $companyId)->where($this->businessKey(), $key)->first();
        }

        // A soft-deleted CRM row still holds the unique (company_id, business key), so creating
        // a replacement would violate the index and fail the whole run. Refuse the row instead
        // of resurrecting something a user deliberately deleted.
        if (!$model && $this->trashedExists($class, $companyId, $key)) {
            $this->issue($run, 'map', 'warning', sprintf(
                '%s %s exists in the CRM but is deleted — restore it to resume syncing.',
                $this->crmEntity(), $key
            ));
            return 'skipped';
        }

        $isNew = !$model;
        if ($isNew) $model = new $class();

        // forceFill: company_id and the business key are guarded on some models, and BC is the
        // authority for both here.
        $model->forceFill($attrs)->save();

        $link->fill([
            'company_id'     => $companyId,
            'mapping_id'     => $mapping->id,
            'crm_type'       => $class,
            'crm_id'         => $model->getKey(),
            'bc_etag'        => $row['@odata.etag'] ?? $link->bc_etag,
            'payload_hash'   => $hash,
            'last_direction' => 'pull',
            'last_synced_at' => now(),
        ])->save();

        return $isNew ? 'created' : 'updated';
    }

    /** OData query: the incremental filter, combined with any configured mapping filter. */
    protected function query(BcEntityMapping $mapping): array
    {
        $fragments = [];

        if ($mapping->sync_cursor) {
            // BC expects ISO-8601 UTC; sync_cursor is a `timestamp` column cast to Carbon.
            $fragments[] = 'lastModifiedDateTime gt '.$mapping->sync_cursor->utc()->format('Y-m-d\TH:i:s\Z');
        }
        foreach ((array) ($mapping->filter ?? []) as $fragment) {
            if (is_string($fragment) && trim($fragment) !== '') $fragments[] = trim($fragment);
        }

        return $fragments ? ['$filter' => implode(' and ', $fragments)] : [];
    }

    /** Highest lastModifiedDateTime seen, as the next cursor. */
    protected function trackCursor(?string $current, array $row): ?string
    {
        $seen = $this->stringOrNull($row['lastModifiedDateTime'] ?? null);
        if ($seen === null) return $current;
        return ($current === null || strcmp($seen, $current) > 0) ? $seen : $current;
    }

    protected function trashedExists(string $class, int $companyId, string $key): bool
    {
        if (!method_exists($class, 'bootSoftDeletes')) return false;

        return $class::withoutGlobalScope('company')->onlyTrashed()
            ->where('company_id', $companyId)->where($this->businessKey(), $key)->exists();
    }

    protected function issue(BcSyncRun $run, string $stage, string $severity, string $message): void
    {
        BcSyncIssue::create([
            'run_id' => $run->id, 'stage' => $stage, 'severity' => $severity,
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    /** BC sends '' for absent strings and occasionally nulls; both mean "no value". */
    protected function stringOrNull($value): ?string
    {
        if ($value === null) return null;
        if (!is_scalar($value)) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    protected function decimalOrNull($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
