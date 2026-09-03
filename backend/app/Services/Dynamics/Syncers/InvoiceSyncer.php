<?php
namespace App\Services\Dynamics\Syncers;

use App\Models\BcEntityMapping;
use App\Models\BcRecordLink;
use App\Models\BcSyncRun;
use App\Models\Invoice;

/**
 * BC `salesInvoices` -> CRM `invoices`: **reconciliation only. Never creates or updates an
 * invoice.**
 *
 * It matches BC invoices to invoices that already exist in the CRM (by number), records the
 * crosswalk, and logs every unmatched BC invoice as a warning — which is the useful output:
 * "these exist in Business Central and not here."
 *
 * Why not import them: CRM invoice money columns (amount_paid, balance, status) are DERIVED by
 * SalesService::recalcInvoice from payments and applied credits. Creating invoices from an
 * external feed would either bypass that derivation or invent payments to satisfy it — the
 * same class of risk that kept Stripe parked. See docs/DYNAMICS_BC_SYNC_SCOPE.md.
 */
class InvoiceSyncer extends PullSyncer
{
    public function crmEntity(): string { return 'invoice'; }
    public function bcEntity(): string  { return 'salesInvoices'; }

    protected function businessKey(): string { return 'invoice_no'; }
    protected function modelClass(): string  { return Invoice::class; }

    protected function defaultFieldMap(): array
    {
        return ['invoice_no' => 'number'];
    }

    /** Unused on this syncer — applyRow is overridden and never writes. */
    protected function toAttributes(array $row, array $map, int $companyId, string $key): array
    {
        return ['invoice_no' => $key];
    }

    /**
     * Link-only override: find the CRM invoice or report it missing. The `payload_hash` here
     * covers the fields we compare for drift reporting, not fields we write.
     */
    protected function applyRow(
        BcRecordLink $link, array $attrs, int $companyId, string $key,
        BcSyncRun $run, BcEntityMapping $mapping, string $hash, array $row
    ): string {
        $invoice = Invoice::withoutGlobalScope('company')
            ->where('company_id', $companyId)->where('invoice_no', $key)->first();

        if (!$invoice) {
            $this->issue($run, 'map', 'warning', sprintf(
                'Business Central invoice %s has no matching CRM invoice — not imported (this syncer reconciles only).',
                $key
            ));
            return 'skipped';
        }

        // Report a total mismatch rather than "fixing" it: which side is right is a business
        // question, and silently overwriting a CRM total would desynchronise amount_paid.
        $bcTotal  = $this->decimalOrNull($row['totalAmountIncludingTax'] ?? null);
        $crmTotal = round((float) $invoice->grand_total, 2);
        if ($bcTotal !== null && abs($bcTotal - $crmTotal) > 0.005) {
            $this->issue($run, 'map', 'warning', sprintf(
                'Invoice %s total differs: Business Central %s vs CRM %s. Not changed.',
                $key, number_format($bcTotal, 2, '.', ''), number_format($crmTotal, 2, '.', '')
            ));
        }

        $isNew = !$link->exists;
        $link->fill([
            'company_id'     => $companyId,
            'mapping_id'     => $mapping->id,
            'crm_type'       => Invoice::class,
            'crm_id'         => $invoice->id,
            'bc_etag'        => $row['@odata.etag'] ?? $link->bc_etag,
            'payload_hash'   => $hash,
            'last_direction' => 'pull',
            'last_synced_at' => now(),
        ])->save();

        return $isNew ? 'created' : 'updated';
    }
}
