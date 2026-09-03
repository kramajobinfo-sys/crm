<?php
namespace App\Services;

use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Customer credits — money owed back to a customer, and its application to invoices.
 *
 * Credits arise three ways: an overpayment (SalesService::recordPayment), an invoice reduced
 * below what was already paid (SalesService::releaseOverApplied), or a staff-issued credit
 * note. Applying one is deliberately NOT recorded as a Payment: `collected_mtd` and the
 * dashboard cash series sum payments.amount, and applying a credit moves no new cash.
 *
 * Out of scope by design (not built, not implied): paying a credit out as a cash refund,
 * auto-applying credit at invoice creation, and cross-currency application.
 */
class CreditService
{
    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return CustomerCredit::query()
            ->with(['customer:id,name,customer_no', 'creator:id,name', 'sourceInvoice:id,invoice_no'])
            ->when(!empty($f['customer_id']), fn ($q) => $q->where('customer_id', $f['customer_id']))
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(($f['status'] ?? null) === 'available', fn ($q) => $q->available())
            ->when(!empty($f['source']), fn ($q) => $q->where('source', $f['source']))
            ->when(!empty($f['q']), fn ($q) => $q->where(fn ($w) =>
                $w->where('credit_no', 'like', '%'.$f['q'].'%')->orWhere('reason', 'like', '%'.$f['q'].'%')))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): CustomerCredit
    {
        return CustomerCredit::with([
            'customer:id,name,customer_no', 'creator:id,name',
            'sourceInvoice:id,invoice_no', 'sourcePayment:id,payment_no',
            'applications' => fn ($q) => $q->with('invoice:id,invoice_no')->orderByDesc('id'),
        ])->findOrFail($id);
    }

    /**
     * Issue a credit. Called by SalesService for system-generated credits (which pass
     * company_id explicitly, since they may run outside a normal request) and by the
     * controller for staff-issued credit notes.
     */
    public function issue(array $data): CustomerCredit
    {
        $companyId = $data['company_id'] ?? auth()->user()?->company_id;

        return CustomerCredit::create([
            'company_id' => $companyId,
            'credit_no' => $this->nextCreditNo($companyId),
            'customer_id' => $data['customer_id'],
            'source' => $data['source'] ?? 'manual',
            'source_invoice_id' => $data['source_invoice_id'] ?? null,
            'source_payment_id' => $data['source_payment_id'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'amount' => round((float) $data['amount'], 2),
            'applied_amount' => 0,
            'status' => 'open',
            'reason' => $data['reason'] ?? null,
            'created_by' => auth()->id(),
            'issued_at' => now(),
        ]);
    }

    /**
     * Apply part or all of a credit to an invoice.
     *
     * The credit row is locked before its remaining balance is read, so two concurrent
     * applications cannot both see the same remainder and spend it twice — the check-then-act
     * shape this codebase already has a documented family of.
     */
    public function apply(int $creditId, int $invoiceId, ?float $amount = null): CreditApplication
    {
        return DB::transaction(function () use ($creditId, $invoiceId, $amount) {
            $credit  = CustomerCredit::whereKey($creditId)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if ($credit->isVoid()) {
                throw new RuntimeException("Credit {$credit->credit_no} has been voided.");
            }
            if ($credit->customer_id !== $invoice->customer_id) {
                throw new RuntimeException('That credit belongs to a different customer.');
            }
            if ($credit->currency !== $invoice->currency) {
                throw new RuntimeException("Credit {$credit->credit_no} is in {$credit->currency}; this invoice is in {$invoice->currency}.");
            }
            if (in_array($invoice->status, ['void', 'draft'], true)) {
                throw new RuntimeException('Credit can only be applied to an issued invoice.');
            }

            // Re-read remaining AFTER the lock.
            $remaining = round((float) $credit->amount - (float) $credit->applied_amount, 2);
            if ($remaining <= 0) {
                throw new RuntimeException("Credit {$credit->credit_no} has nothing left to apply.");
            }

            $applied = (float) $invoice->payments()->sum('applied_amount')
                     + (float) $invoice->creditApplications()->sum('amount');
            $outstanding = round((float) $invoice->grand_total - $applied, 2);
            if ($outstanding <= 0) {
                throw new RuntimeException("{$invoice->invoice_no} is already settled.");
            }

            $take = $amount !== null ? round($amount, 2) : min($remaining, $outstanding);
            if ($take <= 0) {
                throw new RuntimeException('Amount must be greater than zero.');
            }
            if ($take > $remaining) {
                throw new RuntimeException("Only {$remaining} remains on credit {$credit->credit_no}.");
            }
            if ($take > $outstanding) {
                throw new RuntimeException("{$invoice->invoice_no} only has {$outstanding} outstanding.");
            }

            $application = CreditApplication::create([
                'company_id' => $credit->company_id,
                'customer_credit_id' => $credit->id,
                'invoice_id' => $invoice->id,
                'amount' => $take,
                'applied_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Atomic increment rather than writing a recomputed total.
            $credit->increment('applied_amount', $take);
            $credit->refresh();
            if ((float) $credit->applied_amount + 0.001 >= (float) $credit->amount) {
                $credit->forceFill(['status' => 'applied'])->save();
            }

            app(SalesService::class)->refreshInvoiceTotals($invoice->refresh());

            return $application->load('invoice:id,invoice_no', 'credit:id,credit_no');
        });
    }

    /** Void an unapplied credit. Anything already applied must be reversed first. */
    public function void(CustomerCredit $credit, ?string $reason = null): CustomerCredit
    {
        return DB::transaction(function () use ($credit, $reason) {
            $locked = CustomerCredit::whereKey($credit->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoid()) {
                throw new RuntimeException('This credit is already void.');
            }
            if ((float) $locked->applied_amount > 0) {
                throw new RuntimeException(
                    "Credit {$locked->credit_no} has already been applied to an invoice and cannot be voided."
                );
            }

            $locked->forceFill([
                'status' => 'void',
                'reason' => trim(($locked->reason ?? '').($reason ? ' Voided: '.$reason : ' Voided.')),
            ])->save();

            return $this->find($locked->id);
        });
    }

    /** Credits with something left, for the "apply credit" picker on an invoice. */
    public function availableFor(int $customerId, string $currency): \Illuminate\Support\Collection
    {
        return CustomerCredit::available()
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
            ->orderBy('id')
            ->get(['id', 'credit_no', 'amount', 'applied_amount', 'currency', 'source', 'issued_at']);
    }

    public function stats(): array
    {
        return [
            'open_count'      => CustomerCredit::available()->count(),
            'open_value'      => round((float) CustomerCredit::available()
                                    ->selectRaw('COALESCE(SUM(amount - applied_amount),0) AS v')->value('v'), 2),
            'applied_value'   => round((float) CustomerCredit::where('status', '!=', 'void')->sum('applied_amount'), 2),
            'from_overpayment'=> CustomerCredit::where('source', 'overpayment')->count(),
        ];
    }

    private function nextCreditNo(?int $companyId): string
    {
        // Same read-then-insert shape as every other document number in this codebase
        // (nextNumber/nextTicketNo/nextEmployeeNo): unlocked, so two concurrent issues can
        // collide on the unique index. Left consistent with the rest rather than fixed here;
        // the collision surfaces as a clean failure, not silent corruption.
        $last = CustomerCredit::withoutGlobalScope('company')->withTrashed()
            ->where('company_id', $companyId)
            ->where('credit_no', 'like', 'CR-%')
            ->orderByRaw('CAST(SUBSTRING(credit_no, 4) AS UNSIGNED) DESC')
            ->value('credit_no');

        $n = $last ? ((int) substr($last, 3)) + 1 : 1;
        return sprintf('CR-%05d', $n);
    }
}
