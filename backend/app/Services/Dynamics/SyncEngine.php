<?php
namespace App\Services\Dynamics;

use App\Models\BcConnection;
use App\Models\BcEntityMapping;
use App\Models\BcSyncIssue;
use App\Models\BcSyncRun;
use App\Services\Dynamics\Contracts\EntitySyncer;
use App\Services\Dynamics\Exceptions\BcAuthException;
use App\Services\Dynamics\Exceptions\BcRequestException;
use App\Services\Dynamics\Syncers\CompanySyncer;
use App\Services\Dynamics\Syncers\CustomerSyncer;
use App\Services\Dynamics\Syncers\InvoiceSyncer;
use App\Services\Dynamics\Syncers\ItemSyncer;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncEngine
{
    /**
     * crm_entity => syncer class.
     *
     * All four are PULL only (BC -> CRM) — see docs/DYNAMICS_BC_SYNC_SCOPE.md for why push is
     * deliberately absent. `invoice` reconciles rather than imports: it links BC invoices to
     * CRM ones and reports the rest, never creating a financial row.
     *
     * Entities in SUPPORTED_BC_ENTITIES without an entry here (contacts, vendors, sales
     * orders, purchase orders…) can still be configured as mappings, but running one fails
     * loudly rather than silently reporting success on a no-op.
     */
    private const SYNCERS = [
        'company'  => CompanySyncer::class,
        'customer' => CustomerSyncer::class,
        'product'  => ItemSyncer::class,
        'invoice'  => InvoiceSyncer::class,
    ];

    /** Entity sets a mapping may target today; the CRM-side syncer lands with its module. */
    public const SUPPORTED_BC_ENTITIES = [
        'companies', 'customers', 'vendors', 'items', 'contacts',
        'salesOrders', 'salesInvoices', 'salesQuotes', 'purchaseOrders',
        'purchaseInvoices', 'currencies', 'paymentTerms', 'itemCategories',
    ];

    public function syncerFor(string $crmEntity): ?EntitySyncer
    {
        $class = self::SYNCERS[$crmEntity] ?? null;
        return $class ? app($class) : null;
    }

    public function implementedEntities(): array { return array_keys(self::SYNCERS); }

    /** Execute one mapping, always leaving a BcSyncRun row behind. */
    public function run(BcEntityMapping $mapping, string $trigger = 'manual'): BcSyncRun
    {
        $connection = $mapping->connection;

        $run = BcSyncRun::create([
            'company_id'    => $mapping->company_id,
            'connection_id' => $connection->id,
            'mapping_id'    => $mapping->id,
            'direction'     => $mapping->direction,
            'trigger'       => $trigger,
            'status'        => 'running',
            'started_at'    => now(),
        ]);

        try {
            $syncer = $this->syncerFor($mapping->crm_entity);
            if (!$syncer) {
                throw new \RuntimeException(
                    "No syncer implemented for CRM entity '{$mapping->crm_entity}'. ".
                    'It ships with that module.'
                );
            }
            if (!in_array($mapping->direction, $syncer->supportedDirections(), true)) {
                throw new \RuntimeException(
                    "Syncer for '{$mapping->crm_entity}' does not support direction '{$mapping->direction}'."
                );
            }

            $counts = $syncer->sync(new BusinessCentralClient($connection), $mapping, $run);

            $run->fill([
                'created_count' => $counts['created'], 'updated_count' => $counts['updated'],
                'skipped_count' => $counts['skipped'], 'failed_count'  => $counts['failed'],
                'status'        => $counts['failed'] > 0 ? 'partial' : 'success',
                'finished_at'   => now(),
            ])->save();

            $mapping->forceFill(['last_run_at' => now()])->save();
            $connection->forceFill([
                'status' => 'ok', 'last_connected_at' => now(), 'last_error' => null,
            ])->save();
        } catch (BcAuthException $e) {
            $this->failRun($run, 'auth', $e->getMessage());
            $connection->forceFill(['status' => 'auth_failed', 'last_error' => $e->getMessage()])->save();
        } catch (BcRequestException $e) {
            $this->failRun($run, $e->isConflict ? 'conflict' : 'fetch', $e->getMessage(), $e->isConflict);
            $connection->forceFill(['status' => 'unreachable', 'last_error' => $e->getMessage()])->save();
        } catch (Throwable $e) {
            Log::error('BC sync failed', ['mapping' => $mapping->id, 'error' => $e->getMessage()]);
            $this->failRun($run, 'map', $e->getMessage());
        }

        return $run->fresh();
    }

    /** Auth + reachability probe. Never throws — the caller renders the result. */
    public function testConnection(BcConnection $connection): array
    {
        try {
            $client = new BusinessCentralClient($connection);
            $client->accessToken(forceRefresh: true);
            $companies = $client->listCompanies();

            $connection->forceFill([
                'status' => 'ok', 'last_connected_at' => now(), 'last_error' => null,
            ])->save();

            return [
                'ok' => true,
                'message' => 'Authenticated and reached Business Central.',
                'companies' => array_map(fn ($c) => [
                    'id' => $c['id'] ?? null,
                    'name' => $c['name'] ?? null,
                    'display_name' => $c['displayName'] ?? null,
                ], $companies),
            ];
        } catch (BcAuthException $e) {
            $connection->forceFill(['status' => 'auth_failed', 'last_error' => $e->getMessage()])->save();
            return ['ok' => false, 'stage' => 'auth', 'message' => $e->getMessage(), 'companies' => []];
        } catch (BcRequestException $e) {
            $connection->forceFill(['status' => 'unreachable', 'last_error' => $e->getMessage()])->save();
            return ['ok' => false, 'stage' => 'request', 'message' => $e->getMessage(), 'companies' => []];
        } catch (Throwable $e) {
            $connection->forceFill(['status' => 'unreachable', 'last_error' => $e->getMessage()])->save();
            return ['ok' => false, 'stage' => 'unknown', 'message' => $e->getMessage(), 'companies' => []];
        }
    }

    private function failRun(BcSyncRun $run, string $stage, string $message, bool $conflict = false): void
    {
        BcSyncIssue::create([
            'run_id' => $run->id, 'stage' => $stage,
            'severity' => $conflict ? 'conflict' : 'error',
            'message' => mb_substr($message, 0, 1000),
        ]);
        $run->fill([
            'status' => 'failed', 'failed_count' => $run->failed_count + 1,
            'finished_at' => now(), 'error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
