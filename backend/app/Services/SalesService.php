<?php
namespace App\Services;

use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesDocument;
use App\Models\SalesOrder;
use App\Models\TaxRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesService
{
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly EmailService $emails,
        private readonly CreditService $credits,
    ) {}

    /**
     * Public entry point for re-deriving an invoice's money columns. CreditService calls this
     * after applying a credit; recalcInvoice itself stays private so the derivation has one
     * owner. (CreditService resolves SalesService lazily — constructor-injecting both ways
     * would be a container cycle.)
     */
    public function refreshInvoiceTotals(Invoice $invoice): void
    {
        $this->recalcInvoice($invoice);
    }

    // ---- Quotations ------------------------------------------------------

    public function paginateQuotations(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateDocuments(Quotation::query(), $f, $perPage);
    }

    public function findQuotation(int $id): Quotation
    {
        return Quotation::with($this->docRelations())->findOrFail($id);
    }

    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['quote_no'] ??= $this->nextNumber(Quotation::class, 'quote_no', 'QUOT');
            $data['issue_date'] ??= now()->toDateString();
            $data['owner_id'] ??= auth()->id();
            $quote = Quotation::create($data);
            $this->syncItems($quote, $items);
            return $this->findQuotation($quote->id);
        });
    }

    public function updateQuotation(Quotation $quote, array $data): Quotation
    {
        return DB::transaction(function () use ($quote, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);
            $quote->update($data);
            if (is_array($items)) $this->syncItems($quote, $items);
            return $this->findQuotation($quote->id);
        });
    }

    public function setQuotationStatus(Quotation $quote, string $status): Quotation
    {
        $quote->forceFill(['status' => $status])->save();
        return $this->findQuotation($quote->id);
    }

    /**
     * Mark a draft quotation as sent and try to email the customer a link to view/sign it in the
     * portal. The email is best-effort: a mail-server outage must never block the status
     * transition (see EmailService::deliverAndFinalize, which already swallows delivery errors
     * into the Email row's own status rather than throwing).
     */
    public function sendQuotation(Quotation $quote): array
    {
        if ($quote->status !== 'draft') {
            throw new RuntimeException('Only a draft quotation can be sent.');
        }
        $quote->forceFill(['status' => 'sent'])->save();
        $this->workflows->fireEvent('quotations', 'quotation.sent', $quote);
        \App\Models\TimelineActivity::record($quote, 'email', 'Quotation sent');

        $contact = $quote->customer?->contacts()->where('is_primary', true)->first()
            ?? $quote->customer?->contacts()->whereNotNull('email')->first();

        $emailed = false;
        if ($contact?->email) {
            $portalUrl = rtrim(env('FRONTEND_URL') ?: 'http://localhost:8081', '/').'/portal/login';
            $email = $this->emails->compose([
                'to' => [$contact->email],
                'subject' => 'Quotation '.$quote->quote_no.' from '.($quote->customer->name ?? 'us'),
                'body_html' => '<p>Hi '.e($contact->name).',</p>'
                    .'<p>Your quotation <strong>'.e($quote->quote_no).'</strong> is ready to review.</p>'
                    .'<p>Sign in to the customer portal to view the details and sign it: <a href="'.e($portalUrl).'">'.e($portalUrl).'</a></p>',
                'related_type' => 'quotation', 'related_id' => $quote->id,
            ], true);
            $emailed = $email->status === 'sent';
        }

        return ['quotation' => $this->findQuotation($quote->id), 'emailed' => $emailed];
    }

    /**
     * Record a customer's e-signature (typed name + drawn signature image + timestamp/IP) on a
     * sent quotation and mark it accepted. Only a `sent` quotation can be signed — never a draft
     * (never offered to the customer yet) and never a quotation already signed/rejected/expired/
     * converted (no overwriting an existing signature's audit trail).
     */
    public function signQuotation(Quotation $quote, string $signedName, string $signatureData, string $ip): Quotation
    {
        if ($quote->status !== 'sent') {
            throw new RuntimeException('This quotation is not awaiting a signature.');
        }
        $quote->forceFill([
            'status' => 'accepted', 'signed_at' => now(),
            'signed_name' => $signedName, 'signed_ip' => $ip, 'signature_data' => $signatureData,
        ])->save();

        $this->workflows->fireEvent('quotations', 'quotation.signed', $quote);
        \App\Models\TimelineActivity::record($quote, 'system', 'Quotation signed'.($signedName ? ' by '.$signedName : ''));

        return $this->findQuotation($quote->id);
    }

    /** Copy an accepted quotation into a new draft sales order. Refuses a double conversion. */
    public function convertQuotationToOrder(Quotation $quote): SalesOrder
    {
        if ($quote->converted_order_id) {
            throw new RuntimeException('This quotation was already converted to order #'.$quote->converted_order_id.'.');
        }
        return DB::transaction(function () use ($quote) {
            $order = $this->createOrder($this->copyHeader($quote, [
                'order_no' => $this->nextNumber(SalesOrder::class, 'order_no', 'SO'),
                'order_date' => now()->toDateString(),
                'quotation_id' => $quote->id,
                'deal_id' => $quote->deal_id,
                'status' => 'draft',
                'items' => $this->copyItems($quote),
            ]));
            $quote->forceFill(['status' => 'converted', 'converted_order_id' => $order->id])->save();
            return $order;
        });
    }

    // ---- Sales orders ----------------------------------------------------

    public function paginateOrders(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateDocuments(SalesOrder::query(), $f, $perPage);
    }

    public function findOrder(int $id): SalesOrder
    {
        return SalesOrder::with($this->docRelations())->findOrFail($id);
    }

    public function createOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['order_no'] ??= $this->nextNumber(SalesOrder::class, 'order_no', 'SO');
            $data['order_date'] ??= now()->toDateString();
            $data['owner_id'] ??= auth()->id();
            $order = SalesOrder::create($data);
            $this->syncItems($order, $items);
            return $this->findOrder($order->id);
        });
    }

    public function updateOrder(SalesOrder $order, array $data): SalesOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);
            $order->update($data);
            if (is_array($items)) $this->syncItems($order, $items);
            return $this->findOrder($order->id);
        });
    }

    public function setOrderStatus(SalesOrder $order, string $status): SalesOrder
    {
        $order->forceFill(['status' => $status])->save();
        return $this->findOrder($order->id);
    }

    /** Copy a sales order into a new draft-issued invoice. Refuses a double conversion. */
    public function convertOrderToInvoice(SalesOrder $order): Invoice
    {
        if ($order->converted_invoice_id) {
            throw new RuntimeException('This order was already invoiced as #'.$order->converted_invoice_id.'.');
        }
        return DB::transaction(function () use ($order) {
            $invoice = $this->createInvoice($this->copyHeader($order, [
                'invoice_no' => $this->nextNumber(Invoice::class, 'invoice_no', 'INV'),
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'sales_order_id' => $order->id,
                'status' => 'issued',
                'items' => $this->copyItems($order),
            ]));
            $order->forceFill(['converted_invoice_id' => $invoice->id, 'status' => 'fulfilled'])->save();
            return $invoice;
        });
    }

    // ---- Invoices --------------------------------------------------------

    public function paginateInvoices(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateDocuments(Invoice::query()->with('order:id,order_no'), $f, $perPage);
    }

    public function findInvoice(int $id): Invoice
    {
        return Invoice::with(array_merge($this->docRelations(), [
            'payments' => fn ($q) => $q->with('creator:id,name')->latest('received_at'),
            'order:id,order_no',
        ]))->findOrFail($id);
    }

    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);
            $data['invoice_no'] ??= $this->nextNumber(Invoice::class, 'invoice_no', 'INV');
            $data['issue_date'] ??= now()->toDateString();
            $data['owner_id'] ??= auth()->id();
            $data['created_by'] ??= auth()->id();
            $invoice = Invoice::create($data);
            $this->syncItems($invoice, $items);
            $this->recalcInvoice($invoice);
            $this->workflows->fireEvent('invoices', 'invoice.created', $invoice);
            \App\Models\TimelineActivity::record($invoice, 'system', 'Invoice created');
            return $this->findInvoice($invoice->id);
        });
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);
            $invoice->update($data);
            if (is_array($items)) $this->syncItems($invoice, $items);
            // An invoice edited down below what has already been applied leaves money the
            // invoice is no longer entitled to. Hand it back as a credit instead of hiding it
            // behind balance = max(..., 0), which is what used to happen.
            $this->releaseOverApplied($invoice->refresh());
            $this->recalcInvoice($invoice);
            return $this->findInvoice($invoice->id);
        });
    }

    public function setInvoiceStatus(Invoice $invoice, string $status): Invoice
    {
        $invoice->forceFill(['status' => $status])->save();
        return $this->findInvoice($invoice->id);
    }

    /**
     * Record a payment against an invoice, then re-derive amount_paid / balance / status.
     *
     * Cash received beyond what the invoice is owed is not discarded (which is what used to
     * happen — balance clamped to 0 and the excess vanished): the full amount is recorded as
     * cash, only the owed portion is applied to the invoice, and the remainder becomes an
     * open CustomerCredit on the customer's account.
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            // Lock the invoice so two concurrent payments can't both read the same
            // outstanding balance and each decide they are fully applied.
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $alreadyApplied = (float) $locked->payments()->sum('applied_amount')
                            + (float) $locked->creditApplications()->sum('amount');
            $outstanding = max(round((float) $locked->grand_total - $alreadyApplied, 2), 0);

            $received = round((float) $data['amount'], 2);
            $applied  = min($received, $outstanding);
            $excess   = round($received - $applied, 2);

            $payment = Payment::create([
                'company_id' => $locked->company_id,
                'payment_no' => $data['payment_no'] ?? $this->nextNumber(Payment::class, 'payment_no', 'PAY'),
                'invoice_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'method' => $data['method'] ?? 'bank_transfer',
                'amount' => $received,
                'applied_amount' => $applied,
                'currency' => $locked->currency,
                'received_at' => $data['received_at'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($excess > 0 && $locked->customer_id) {
                $this->credits->issue([
                    'company_id' => $locked->company_id,
                    'customer_id' => $locked->customer_id,
                    'source' => 'overpayment',
                    'source_invoice_id' => $locked->id,
                    'source_payment_id' => $payment->id,
                    'currency' => $locked->currency,
                    'amount' => $excess,
                    'reason' => "Overpayment on {$locked->invoice_no}.",
                ]);
            }

            $this->recalcInvoice($locked->refresh());
            return $payment->load('creator:id,name', 'credit:id,credit_no,amount');
        });
    }

    // ---- Payments (standalone list) -------------------------------------

    public function paginatePayments(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['invoice:id,invoice_no', 'customer:id,name,customer_no', 'creator:id,name'])
            // The OR pair must be its own group: ungrouped, SQL's AND-binds-tighter-than-OR
            // turned `?q=X&customer_id=7` into
            //   (payment_no LIKE X) OR (reference LIKE X AND customer_id = 7)
            // so every payment matching the text came back regardless of customer or method.
            ->when(!empty($f['q']), fn ($q) => $q->where(fn ($w) =>
                $w->where('payment_no', 'like', '%'.$f['q'].'%')
                  ->orWhere('reference', 'like', '%'.$f['q'].'%')))
            ->when(!empty($f['method']), fn ($q) => $q->where('method', $f['method']))
            ->when(!empty($f['customer_id']), fn ($q) => $q->where('customer_id', $f['customer_id']))
            ->orderByDesc('received_at')
            ->paginate($perPage);
    }

    /**
     * Deleting a payment must also deal with any credit its overpayment created, or the
     * credit outlives the cash behind it — and if it had been applied elsewhere, that other
     * invoice would be settled with money that no longer exists. Unapplied: void it with the
     * payment. Already applied: refuse, and say which credit is in the way.
     */
    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $credit = CustomerCredit::where('source_payment_id', $payment->id)->lockForUpdate()->first();

            if ($credit && !$credit->isVoid() && (float) $credit->applied_amount > 0) {
                throw new RuntimeException(
                    "This payment's overpayment credit {$credit->credit_no} has already been applied to "
                    ."another invoice. Reverse that application before deleting the payment."
                );
            }

            if ($credit && !$credit->isVoid()) {
                $credit->forceFill(['status' => 'void', 'reason' => trim(($credit->reason ?? '').' Voided: source payment deleted.')])->save();
            }

            $invoice = $payment->invoice;
            $payment->delete();
            if ($invoice) $this->recalcInvoice($invoice->refresh());
        });
    }

    // ---- stats -----------------------------------------------------------

    public function stats(): array
    {
        return [
            'quotations_open'   => Quotation::whereIn('status', ['draft','sent'])->count(),
            'orders_open'       => SalesOrder::whereIn('status', ['draft','confirmed','processing'])->count(),
            'invoices_unpaid'   => Invoice::outstanding()->count(),
            'outstanding_value' => (float) Invoice::outstanding()->sum('balance'),
            'revenue_mtd'       => (float) Invoice::whereIn('status', Invoice::REVENUE_STATUSES)
                ->whereMonth('issue_date', now()->month)->whereYear('issue_date', now()->year)->sum('grand_total'),
            'collected_mtd'     => (float) Payment::whereMonth('received_at', now()->month)->whereYear('received_at', now()->year)->sum('amount'),
        ];
    }

    // ---- shared helpers --------------------------------------------------

    private function docRelations(): array
    {
        return [
            'customer:id,name,customer_no', 'owner:id,name', 'branch:id,name',
            'items' => fn ($q) => $q->with('taxRate:id,name,rate'),
        ];
    }

    private function paginateDocuments($query, array $f, int $perPage): LengthAwarePaginator
    {
        return $query
            ->with(['customer:id,name,customer_no', 'owner:id,name'])
            ->search($f['q'] ?? null)
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['customer_id']), fn ($q) => $q->where('customer_id', $f['customer_id']))
            ->when(!empty($f['owner_id']) && $f['owner_id'] === 'me', fn ($q) => $q->where('owner_id', auth()->id()))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Replace a document's line items and roll totals up. Each line's effective tax rate is
     * the line's own tax_rate_id, else the product's default; the rate/inclusive flag come
     * from the tax_rates table so the client cannot spoof them.
     */
    private function syncItems(SalesDocument $doc, array $lines): void
    {
        $doc->items()->delete();

        $taxCache = [];
        $resolveTax = function (?int $taxRateId) use (&$taxCache): array {
            if (!$taxRateId) return [0.0, false, null];
            if (!array_key_exists($taxRateId, $taxCache)) {
                $tr = TaxRate::find($taxRateId);
                $taxCache[$taxRateId] = $tr ? [(float) $tr->rate, (bool) $tr->is_inclusive, $tr->id] : [0.0, false, null];
            }
            return $taxCache[$taxRateId];
        };

        // Products carry a default tax rate; pre-load the ones referenced by lines.
        $productTax = Product::whereIn('id', collect($lines)->pluck('product_id')->filter()->all())
            ->pluck('tax_rate_id', 'id');

        foreach (array_values($lines) as $i => $line) {
            $taxRateId = $line['tax_rate_id'] ?? ($productTax[$line['product_id'] ?? null] ?? null);
            [$rate, $inclusive, $trId] = $resolveTax($taxRateId);

            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) ($line['unit_price'] ?? 0);
            $disc = (float) ($line['discount_pct'] ?? 0);
            [$lineTotal, $taxAmount] = \App\Models\SalesDocumentItem::computeLine($qty, $price, $disc, $rate, $inclusive);

            $doc->items()->create([
                'company_id' => $doc->company_id,
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Item',
                'description' => $line['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_pct' => $disc,
                'tax_rate_id' => $trId,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'sort_order' => $i,
            ]);
        }

        $doc->recomputeTotals();
    }

    /**
     * If more is applied to an invoice than it is now owed, unwind the excess and issue it as
     * a credit. Payment applications are trimmed newest-first (credit applications are left
     * alone — those are already credit and unwinding them would just move money sideways).
     */
    private function releaseOverApplied(Invoice $invoice): void
    {
        $applied = (float) $invoice->payments()->sum('applied_amount')
                 + (float) $invoice->creditApplications()->sum('amount');
        $excess  = round($applied - (float) $invoice->grand_total, 2);
        if ($excess <= 0 || !$invoice->customer_id) return;

        $remaining = $excess;
        foreach ($invoice->payments()->orderByDesc('id')->get() as $payment) {
            if ($remaining <= 0) break;
            $cut = min((float) $payment->applied_amount, $remaining);
            if ($cut <= 0) continue;
            $payment->forceFill(['applied_amount' => round((float) $payment->applied_amount - $cut, 2)])->save();
            $remaining = round($remaining - $cut, 2);
        }

        // Only the portion actually reclaimed from payments becomes a credit.
        $issued = round($excess - $remaining, 2);
        if ($issued <= 0) return;

        $this->credits->issue([
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'source' => 'invoice_adjustment',
            'source_invoice_id' => $invoice->id,
            'currency' => $invoice->currency,
            'amount' => $issued,
            'reason' => "{$invoice->invoice_no} reduced below the amount already paid.",
        ]);
    }

    /**
     * Re-derive amount_paid / balance / status from what has actually been applied to the
     * invoice: the applied portion of its payments, plus any customer credits applied to it.
     *
     * Sums `applied_amount`, NOT `amount`: `amount` is the cash received (which may exceed
     * what this invoice was owed — the excess becomes a CustomerCredit), while
     * `applied_amount` is the part that settles this invoice. Because the split is decided
     * at intake, an invoice edited upward later cannot silently absorb money already handed
     * to a credit.
     */
    private function recalcInvoice(Invoice $invoice): void
    {
        $paid = (float) $invoice->payments()->sum('applied_amount')
              + (float) $invoice->creditApplications()->sum('amount');
        $grand = (float) $invoice->grand_total;
        $status = $invoice->status;
        $wasPaid = $invoice->status === 'paid';

        // Only auto-move between the money states; leave draft/void/issued-with-no-pay alone.
        if (!in_array($status, ['void'], true)) {
            if ($paid <= 0) {
                $status = $status === 'draft' ? 'draft' : 'issued';
            } elseif ($paid + 0.001 < $grand) {
                $status = 'partially_paid';
            } else {
                $status = 'paid';
            }
        }

        $invoice->forceFill([
            'amount_paid' => round($paid, 2),
            'balance' => round(max($grand - $paid, 0), 2),
            'status' => $status,
        ])->save();

        if ($status === 'paid' && !$wasPaid) { $this->workflows->fireEvent('invoices', 'invoice.paid', $invoice); \App\Models\TimelineActivity::record($invoice, 'system', 'Invoice paid'); }
    }

    private function copyHeader(SalesDocument $src, array $overrides): array
    {
        return array_merge([
            'customer_id' => $src->customer_id,
            'owner_id' => $src->owner_id,
            'branch_id' => $src->branch_id,
            'currency' => $src->currency,
            'notes' => $src->notes,
            'terms' => $src->terms,
        ], $overrides);
    }

    private function copyItems(SalesDocument $src): array
    {
        return $src->items()->get()->map(fn ($it) => [
            'product_id' => $it->product_id,
            'name' => $it->name,
            'description' => $it->description,
            'quantity' => (float) $it->quantity,
            'unit_price' => (float) $it->unit_price,
            'discount_pct' => (float) $it->discount_pct,
            'tax_rate_id' => $it->tax_rate_id,
        ])->all();
    }

    /** Sequential per-company document number, e.g. INV-00042. */
    private function nextNumber(string $modelClass, string $column, string $prefix): string
    {
        $companyId = auth()->user()?->company_id;
        $q = $modelClass::withoutGlobalScopes();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $q = $q->withTrashed();
        }
        $last = $q->where('company_id', $companyId)
            ->where($column, 'like', $prefix.'-%')
            ->orderByRaw("CAST(SUBSTRING({$column}, ?) AS UNSIGNED) DESC", [strlen($prefix) + 2])
            ->value($column);
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }
}
