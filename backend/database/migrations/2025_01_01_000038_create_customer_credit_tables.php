<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credits (credit notes) — the accounting home for money a customer has paid us
 * that no invoice is entitled to.
 *
 * Before this, an overpayment silently vanished: recalcInvoice stored
 * balance = max(grand_total - paid, 0), so a 1000 payment on a 100 invoice recorded
 * amount_paid = 1000, balance = 0.00, and counted 1000 toward collected_mtd with no record
 * of the 900 owed back. Reducing an invoice's total below what was already paid hid the
 * difference the same way.
 *
 * `payments.applied_amount` is the primitive that makes this work: `amount` stays the cash
 * actually received (so collected_mtd and the dashboard cash series remain true), while
 * `applied_amount` is how much of it settles its invoice. recalcInvoice sums applied_amount
 * plus applied credits, which makes the derivation deterministic and immune to later invoice
 * edits — capping amount_paid instead would double-count the excess if the invoice were
 * later edited upward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Nullable-free: every payment has an applied portion. Backfilled below.
            $table->decimal('applied_amount', 14, 2)->default(0)->after('amount');
        });

        // Existing payments were fully applied by definition — the old recalc summed `amount`.
        DB::statement('UPDATE payments SET applied_amount = amount');

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('credit_no', 32);
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Where the credit came from. `overpayment` and `invoice_adjustment` are minted by
            // the system; `manual` is a staff-issued credit note.
            $table->enum('source', ['overpayment', 'invoice_adjustment', 'manual'])->default('manual');
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            // Lets deletePayment find and reverse the credit its payment spawned. Without it a
            // deleted overpayment would leave a credit with no cash behind it.
            $table->foreignId('source_payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 14, 2);
            // Maintained, not derived on read, so `remaining` is a plain subtraction and can be
            // incremented atomically under a row lock.
            $table->decimal('applied_amount', 14, 2)->default(0);
            $table->enum('status', ['open', 'applied', 'void'])->default('open');
            $table->string('reason', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'credit_no']);
            $table->index(['company_id', 'customer_id', 'status']);
        });

        // Ledger of how each credit was consumed — one row per application to an invoice.
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_credit_id')->constrained('customer_credits')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'customer_credit_id']);
        });

        $this->surfaceHistoricalOverpayments();
    }

    /**
     * Repair any invoice already carrying more applied money than it is owed, minting the
     * difference as a credit instead of leaving it hidden. There were zero such rows when this
     * was written (verified), but the window between writing and deploying is real, and the
     * whole point of this feature is that such money must not be invisible.
     */
    private function surfaceHistoricalOverpayments(): void
    {
        $over = DB::table('invoices')
            ->select('invoices.id', 'invoices.company_id', 'invoices.customer_id', 'invoices.currency',
                'invoices.grand_total', DB::raw('COALESCE(SUM(payments.applied_amount),0) AS applied'))
            ->leftJoin('payments', function ($j) {
                $j->on('payments.invoice_id', '=', 'invoices.id')->whereNull('payments.deleted_at');
            })
            ->whereNull('invoices.deleted_at')
            ->groupBy('invoices.id', 'invoices.company_id', 'invoices.customer_id', 'invoices.currency', 'invoices.grand_total')
            ->havingRaw('applied > invoices.grand_total + 0.001')
            ->get();

        foreach ($over as $inv) {
            $excess = round((float) $inv->applied - (float) $inv->grand_total, 2);
            if ($excess <= 0 || !$inv->customer_id) continue;

            // Trim the newest payments' applied portions until the invoice is exactly settled.
            $remaining = $excess;
            $payments = DB::table('payments')->where('invoice_id', $inv->id)->whereNull('deleted_at')
                ->orderByDesc('id')->get(['id', 'applied_amount']);
            foreach ($payments as $p) {
                if ($remaining <= 0) break;
                $cut = min((float) $p->applied_amount, $remaining);
                DB::table('payments')->where('id', $p->id)
                    ->update(['applied_amount' => round((float) $p->applied_amount - $cut, 2)]);
                $remaining = round($remaining - $cut, 2);
            }

            $seq = DB::table('customer_credits')->where('company_id', $inv->company_id)->count() + 1;
            DB::table('customer_credits')->insert([
                'company_id' => $inv->company_id,
                'credit_no' => sprintf('CR-%05d', $seq),
                'customer_id' => $inv->customer_id,
                'source' => 'invoice_adjustment',
                'source_invoice_id' => $inv->id,
                'currency' => $inv->currency ?: 'USD',
                'amount' => $excess,
                'applied_amount' => 0,
                'status' => 'open',
                'reason' => 'Surfaced by migration: invoice carried more paid than owed.',
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoices')->where('id', $inv->id)->update([
                'amount_paid' => $inv->grand_total,
                'balance' => 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('customer_credits');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('applied_amount');
        });
    }
};
