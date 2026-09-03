<?php
namespace App\Services\Dynamics\Syncers;

use App\Models\BcEntityMapping;
use App\Models\BcRecordLink;
use App\Models\BcSyncIssue;
use App\Models\BcSyncRun;
use App\Models\Company;
use App\Services\Dynamics\BusinessCentralClient;
use App\Services\Dynamics\Contracts\EntitySyncer;

/**
 * Reference implementation: pulls Business Central companies and records the
 * crosswalk against the CRM's own Company.
 *
 * Deliberately read-only. It exists because `companies` is one of the few CRM
 * tables that exists today (migrations stop at 000006 + chat + these), so the
 * engine, crosswalk, counters and issue logging get exercised by a real path
 * rather than sitting behind an abstraction nothing implements.
 *
 * Customer/Item/Invoice syncers arrive with Modules 3, 7 and 9.
 */
class CompanySyncer implements EntitySyncer
{
    public function crmEntity(): string { return 'company'; }
    public function bcEntity(): string  { return 'companies'; }
    public function supportedDirections(): array { return ['pull']; }

    public function sync(BusinessCentralClient $client, BcEntityMapping $mapping, BcSyncRun $run): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        $remote = $client->listCompanies();
        $local  = Company::withoutGlobalScopes()->find($mapping->company_id);

        if (!$local) {
            BcSyncIssue::create([
                'run_id' => $run->id, 'stage' => 'map', 'severity' => 'error',
                'message' => 'CRM company '.$mapping->company_id.' no longer exists.',
            ]);
            $counts['failed']++;
            return $counts;
        }

        foreach ($remote as $row) {
            $bcId = $row['id'] ?? null;
            if (!$bcId) { $counts['skipped']++; continue; }

            $hash = hash('sha256', json_encode([
                $row['name'] ?? null, $row['displayName'] ?? null, $row['systemVersion'] ?? null,
            ]));

            $link = BcRecordLink::withoutGlobalScopes()->firstOrNew([
                'connection_id' => $mapping->connection_id,
                'bc_entity'     => $this->bcEntity(),
                'bc_id'         => $bcId,
            ]);

            $isNew = !$link->exists;
            if (!$isNew && $link->payload_hash === $hash) { $counts['skipped']++; continue; }

            $link->fill([
                'company_id'     => $mapping->company_id,
                'mapping_id'     => $mapping->id,
                'crm_type'       => Company::class,
                'crm_id'         => $local->id,
                'bc_etag'        => $row['@odata.etag'] ?? $link->bc_etag,
                'payload_hash'   => $hash,
                'last_direction' => 'pull',
                'last_synced_at' => now(),
            ])->save();

            $isNew ? $counts['created']++ : $counts['updated']++;
        }

        return $counts;
    }
}
