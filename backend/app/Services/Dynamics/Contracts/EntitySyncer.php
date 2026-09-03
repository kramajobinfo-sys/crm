<?php
namespace App\Services\Dynamics\Contracts;

use App\Models\BcEntityMapping;
use App\Models\BcSyncRun;
use App\Services\Dynamics\BusinessCentralClient;

/**
 * One CRM entity <-> one Business Central entity set.
 *
 * Most CRM modules (Customers M3, Sales M7, Inventory M9…) do not exist yet, so
 * concrete syncers are added as those tables ship. The engine, crosswalk and run
 * logging are entity-agnostic and already work.
 */
interface EntitySyncer
{
    /** CRM-side key stored in bc_entity_mappings.crm_entity. */
    public function crmEntity(): string;

    /** Default BC OData entity set, e.g. 'customers'. */
    public function bcEntity(): string;

    /** Directions this syncer actually implements. */
    public function supportedDirections(): array;

    /**
     * Perform the sync and return counters.
     *
     * @return array{created:int,updated:int,skipped:int,failed:int}
     */
    public function sync(
        BusinessCentralClient $client,
        BcEntityMapping $mapping,
        BcSyncRun $run,
    ): array;
}
