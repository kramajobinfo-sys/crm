<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\SalesDocumentItem;
use App\Models\SalesOrder;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $users = User::where('company_id', $cid)
            ->whereIn('email', ['sales.mgr@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $mgr = $users['sales.mgr@krama.local'] ?? null;
        $rep = $users['sales@krama.local'] ?? $mgr;

        // Tax rates -------------------------------------------------------
        $vat = TaxRate::updateOrCreate(
            ['company_id' => $cid, 'code' => 'VAT5'],
            ['name' => 'VAT 5%', 'rate' => 5, 'is_inclusive' => false, 'is_default' => true, 'is_active' => true]
        );
        TaxRate::updateOrCreate(
            ['company_id' => $cid, 'code' => 'ZERO'],
            ['name' => 'Zero-rated', 'rate' => 0, 'is_inclusive' => false, 'is_default' => false, 'is_active' => true]
        );

        // Categories ------------------------------------------------------
        $cats = [];
        foreach ([['Office furniture','OFFICE'],['Seating','SEATING'],['Lighting','LIGHT'],['Services','SERVICE']] as [$n,$c]) {
            $cats[$c] = ProductCategory::updateOrCreate(
                ['company_id' => $cid, 'code' => $c], ['name' => $n, 'is_active' => true]
            );
        }

        // Products --------------------------------------------------------
        // [sku, name, category, type, unit, cost, sale]
        $rows = [
            ['SKU-00001','Executive desk (walnut)','OFFICE','goods','pcs',2800,4500],
            ['SKU-00002','Ergonomic office chair','SEATING','goods','pcs',650,1200],
            ['SKU-00003','Meeting table 10-seat','OFFICE','goods','pcs',5200,8500],
            ['SKU-00004','Modular sofa set','SEATING','goods','set',2400,3800],
            ['SKU-00005','Accent floor lamp','LIGHT','goods','pcs',180,320],
            ['SKU-00006','Storage cabinet','OFFICE','goods','pcs',480,780],
            ['SKU-00007','Interior design consultation','SERVICE','service','hr',0,350],
            ['SKU-00008','Delivery & installation','SERVICE','service','job',0,600],
        ];
        $products = [];
        foreach ($rows as [$sku,$name,$cat,$type,$unit,$cost,$sale]) {
            $products[$sku] = Product::updateOrCreate(
                ['company_id' => $cid, 'sku' => $sku],
                ['name' => $name, 'category_id' => $cats[$cat]->id, 'type' => $type, 'unit' => $unit,
                 'cost_price' => $cost, 'sale_price' => $sale, 'tax_rate_id' => $vat->id,
                 'track_inventory' => $type === 'goods', 'is_active' => true]
            );
        }

        $custByNo = Customer::withoutGlobalScopes()->where('company_id', $cid)->pluck('id', 'customer_no');
        $vatRate = 5.0;

        // Helper to build item rows with computed totals.
        $buildItems = function (array $lines) use ($vat, $vatRate) {
            $items = [];
            foreach (array_values($lines) as $i => [$product, $qty, $disc]) {
                $price = (float) $product->sale_price;
                [$lineTotal, $taxAmount] = SalesDocumentItem::computeLine($qty, $price, $disc, $vatRate, false);
                $items[] = [
                    'company_id' => $product->company_id, 'product_id' => $product->id, 'name' => $product->name,
                    'quantity' => $qty, 'unit_price' => $price, 'discount_pct' => $disc,
                    'tax_rate_id' => $vat->id, 'tax_amount' => $taxAmount, 'line_total' => $lineTotal, 'sort_order' => $i,
                ];
            }
            return $items;
        };

        // Quotation (sent) ------------------------------------------------
        $quote = Quotation::updateOrCreate(
            ['company_id' => $cid, 'quote_no' => 'QUOT-00001'],
            ['customer_id' => $custByNo['CUST-00001'] ?? null, 'owner_id' => $mgr, 'currency' => 'AED',
             'status' => 'sent', 'converted_order_id' => null,
             'issue_date' => now()->subDays(6)->toDateString(),
             'valid_until' => now()->addDays(24)->toDateString()]
        );
        $this->replaceItems($quote, $buildItems([
            [$products['SKU-00001'], 12, 5], [$products['SKU-00002'], 40, 10], [$products['SKU-00003'], 3, 0],
        ]));

        // Sales order (confirmed) -----------------------------------------
        $order = SalesOrder::updateOrCreate(
            ['company_id' => $cid, 'order_no' => 'SO-00001'],
            ['customer_id' => $custByNo['CUST-00002'] ?? null, 'owner_id' => $mgr, 'currency' => 'AED',
             'status' => 'confirmed', 'order_date' => now()->subDays(3)->toDateString()]
        );
        $this->replaceItems($order, $buildItems([[$products['SKU-00004'], 15, 8], [$products['SKU-00005'], 30, 0]]));

        // Invoices --------------------------------------------------------
        // Paid invoice (Emaar) — a payment covers it in full.
        $invPaid = Invoice::updateOrCreate(
            ['company_id' => $cid, 'invoice_no' => 'INV-00001'],
            ['customer_id' => $custByNo['CUST-00008'] ?? null, 'owner_id' => $mgr, 'created_by' => $mgr,
             'currency' => 'AED', 'status' => 'issued', 'issue_date' => now()->subDays(20)->toDateString(),
             'due_date' => now()->addDays(10)->toDateString()]
        );
        $this->replaceItems($invPaid, $buildItems([[$products['SKU-00004'], 6, 10], [$products['SKU-00003'], 2, 0]]));
        $this->recalc($invPaid);
        Payment::updateOrCreate(
            ['company_id' => $cid, 'payment_no' => 'PAY-00001'],
            ['invoice_id' => $invPaid->id, 'customer_id' => $invPaid->customer_id, 'method' => 'bank_transfer',
             'amount' => $invPaid->grand_total, 'currency' => 'AED', 'received_at' => now()->subDays(15),
             'reference' => 'TT-99120', 'created_by' => $mgr]
        );
        $this->recalc($invPaid);

        // Partially paid invoice (Al Futtaim).
        $invPartial = Invoice::updateOrCreate(
            ['company_id' => $cid, 'invoice_no' => 'INV-00002'],
            ['customer_id' => $custByNo['CUST-00001'] ?? null, 'owner_id' => $mgr, 'created_by' => $mgr,
             'currency' => 'AED', 'status' => 'issued', 'issue_date' => now()->subDays(8)->toDateString(),
             'due_date' => now()->addDays(22)->toDateString()]
        );
        $this->replaceItems($invPartial, $buildItems([[$products['SKU-00001'], 8, 0], [$products['SKU-00006'], 10, 5]]));
        $this->recalc($invPartial);
        Payment::updateOrCreate(
            ['company_id' => $cid, 'payment_no' => 'PAY-00002'],
            ['invoice_id' => $invPartial->id, 'customer_id' => $invPartial->customer_id, 'method' => 'cheque',
             'amount' => round($invPartial->grand_total * 0.4, 2), 'currency' => 'AED',
             'received_at' => now()->subDays(4), 'reference' => 'CHQ-4471', 'created_by' => $rep]
        );
        $this->recalc($invPartial);

        // Open (unpaid) invoice.
        $invOpen = Invoice::updateOrCreate(
            ['company_id' => $cid, 'invoice_no' => 'INV-00003'],
            ['customer_id' => $custByNo['CUST-00003'] ?? null, 'owner_id' => $rep, 'created_by' => $rep,
             'currency' => 'AED', 'status' => 'issued', 'issue_date' => now()->subDays(2)->toDateString(),
             'due_date' => now()->addDays(28)->toDateString()]
        );
        $this->replaceItems($invOpen, $buildItems([[$products['SKU-00005'], 20, 0], [$products['SKU-00007'], 8, 0]]));
        $this->recalc($invOpen);

        $this->command?->info('Seeded 2 tax rates, '.count($cats).' categories, '.count($rows).' products, 1 quotation, 1 order, 3 invoices, 2 payments.');
    }

    /** Replace a document's items and roll totals up (seeder runs unauthenticated). */
    private function replaceItems($document, array $items): void
    {
        $document->items()->delete();
        foreach ($items as $row) $document->items()->create($row);
        $document->recomputeTotals();
    }

    /** Re-derive invoice amount_paid / balance / status from its payments. */
    private function recalc(Invoice $invoice): void
    {
        $invoice->refresh();
        $paid = (float) $invoice->payments()->sum('amount');
        $grand = (float) $invoice->grand_total;
        $status = $paid <= 0 ? 'issued' : ($paid + 0.001 < $grand ? 'partially_paid' : 'paid');
        $invoice->forceFill([
            'amount_paid' => round($paid, 2),
            'balance' => round(max($grand - $paid, 0), 2),
            'status' => $status,
        ])->save();
    }
}
