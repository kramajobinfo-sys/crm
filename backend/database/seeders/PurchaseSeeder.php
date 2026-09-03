<?php
namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesDocumentItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $users = User::where('company_id', $cid)
            ->whereIn('email', ['purchase@krama.local', 'ceo@krama.local'])
            ->pluck('id', 'email');
        $buyer = $users['purchase@krama.local'] ?? null;
        $ceo   = $users['ceo@krama.local'] ?? null;

        // Vendors ---------------------------------------------------------
        $vendors = [];
        foreach ([
            ['VEN-00001','Emirates Timber Trading','ap@emiratestimber.example','AED',30],
            ['VEN-00002','Gulf Upholstery Supplies','orders@gulfupholstery.example','AED',45],
            ['VEN-00003','Lumina Lighting FZE','sales@luminalighting.example','AED',15],
            ['VEN-00004','Metro Hardware & Fittings','info@metrohardware.example','AED',30],
        ] as [$no,$name,$email,$ccy,$terms]) {
            $vendors[$no] = Vendor::updateOrCreate(
                ['company_id' => $cid, 'vendor_no' => $no],
                ['name' => $name, 'email' => $email, 'currency' => $ccy,
                 'payment_terms_days' => $terms, 'status' => 'active']
            );
        }

        // Approval workflow: POs at/above AED 50k need buyer then CEO.
        ApprovalWorkflow::updateOrCreate(
            ['company_id' => $cid, 'name' => 'High-value PO approval'],
            ['document_type' => 'purchase_order', 'min_amount' => 50000,
             'approver_ids' => array_values(array_filter([$buyer, $ceo])), 'is_active' => true]
        );

        $productsBySku = Product::withoutGlobalScopes()->where('company_id', $cid)->get()->keyBy('sku');

        // Purchase request (approved, low value so it auto-approved) -------
        $pr = PurchaseRequest::updateOrCreate(
            ['company_id' => $cid, 'pr_no' => 'PR-00001'],
            ['requested_by' => $buyer, 'status' => 'approved',
             'needed_by' => now()->addDays(14)->toDateString(), 'notes' => 'Showroom restock']
        );
        $pr->items()->delete();
        $prTotal = 0.0;
        foreach ([['SKU-00005', 40, 180], ['SKU-00006', 15, 480]] as $i => [$sku, $qty, $price]) {
            $p = $productsBySku[$sku] ?? null; if (!$p) continue;
            $prTotal += $qty * $price;
            $pr->items()->create([
                'company_id' => $cid, 'product_id' => $p->id, 'name' => $p->name,
                'quantity' => $qty, 'estimated_price' => $price, 'sort_order' => $i,
            ]);
        }
        $pr->forceFill(['estimated_total' => round($prTotal, 2)])->save();

        // Purchase orders -------------------------------------------------
        $this->seedOrder($cid, 'PO-00001', $vendors['VEN-00001'], $buyer, 'confirmed', $productsBySku, [
            ['SKU-00001', 10, 2800], ['SKU-00003', 3, 5200],
        ]);
        $this->seedOrder($cid, 'PO-00002', $vendors['VEN-00003'], $buyer, 'draft', $productsBySku, [
            ['SKU-00005', 50, 180],
        ]);

        $this->command?->info('Seeded '.count($vendors).' vendors, 1 workflow, 1 request, 2 purchase orders.');
    }

    private function seedOrder(int $cid, string $no, Vendor $vendor, ?int $buyer, string $status, $products, array $lines): void
    {
        $po = PurchaseOrder::updateOrCreate(
            ['company_id' => $cid, 'po_no' => $no],
            ['vendor_id' => $vendor->id, 'created_by' => $buyer, 'status' => $status,
             'order_date' => now()->subDays(4)->toDateString(),
             'expected_date' => now()->addDays(10)->toDateString(), 'currency' => 'AED']
        );
        $po->items()->delete();
        foreach (array_values($lines) as $i => [$sku, $qty, $price]) {
            $p = $products[$sku] ?? null; if (!$p) continue;
            [$lineTotal, $tax] = SalesDocumentItem::computeLine($qty, $price, 0, 5, false);
            $po->items()->create([
                'company_id' => $cid, 'product_id' => $p->id, 'name' => $p->name,
                'quantity' => $qty, 'unit_price' => $price, 'discount_pct' => 0,
                'tax_amount' => $tax, 'line_total' => $lineTotal, 'sort_order' => $i,
            ]);
        }
        $po->recomputeTotals();
    }
}
