<?php
namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $mgr = User::where('company_id', $cid)->where('email', 'warehouse@krama.local')->value('id')
            ?? User::where('company_id', $cid)->where('email', 'sales.mgr@krama.local')->value('id');

        $main = Warehouse::updateOrCreate(
            ['company_id' => $cid, 'code' => 'MAIN'],
            ['name' => 'Main warehouse', 'address' => 'Industrial Area 4, Dubai', 'is_default' => true, 'is_active' => true]
        );
        $store = Warehouse::updateOrCreate(
            ['company_id' => $cid, 'code' => 'STORE'],
            ['name' => 'Showroom store', 'address' => 'Sheikh Zayed Rd, Dubai', 'is_default' => false, 'is_active' => true]
        );

        // Opening balances for goods, written as receipt movements so the ledger reconciles.
        // [sku, mainQty, storeQty]
        $opening = [
            ['SKU-00001', 40, 6], ['SKU-00002', 120, 20], ['SKU-00003', 12, 2],
            ['SKU-00004', 25, 4], ['SKU-00005', 80, 15], ['SKU-00006', 8, 3],
        ];
        $productsBySku = Product::withoutGlobalScopes()->where('company_id', $cid)->pluck('id', 'sku');

        // Reset this company's inventory ledger so re-runs stay idempotent.
        StockMovement::withoutGlobalScopes()->where('company_id', $cid)->delete();
        StockItem::withoutGlobalScopes()->where('company_id', $cid)->delete();

        $count = 0;
        foreach ($opening as [$sku, $mainQty, $storeQty]) {
            $pid = $productsBySku[$sku] ?? null;
            if (!$pid) continue;
            foreach ([[$main->id, $mainQty], [$store->id, $storeQty]] as [$whId, $qty]) {
                if ($qty <= 0) continue;
                StockItem::updateOrCreate(
                    ['product_id' => $pid, 'warehouse_id' => $whId],
                    ['company_id' => $cid, 'quantity' => $qty]
                );
                StockMovement::create([
                    'company_id' => $cid, 'product_id' => $pid, 'warehouse_id' => $whId,
                    'type' => 'receipt', 'quantity' => $qty, 'balance_after' => $qty,
                    'reference' => 'OPENING', 'note' => 'Opening balance', 'user_id' => $mgr,
                    'occurred_at' => now()->subDays(30),
                ]);
                $count++;
            }
        }

        // Barcodes for a couple of products.
        foreach ([['SKU-00001','6291041500213'],['SKU-00002','6291041500220'],['SKU-00005','6291041500251']] as [$sku,$code]) {
            $pid = $productsBySku[$sku] ?? null;
            if ($pid) {
                Barcode::updateOrCreate(
                    ['company_id' => $cid, 'barcode' => $code],
                    ['product_id' => $pid, 'type' => 'EAN13', 'is_primary' => true]
                );
            }
        }

        // A draft transfer MAIN → STORE (not yet shipped, so no stock moved).
        $transfer = StockTransfer::updateOrCreate(
            ['company_id' => $cid, 'transfer_no' => 'TRF-00001'],
            ['from_warehouse_id' => $main->id, 'to_warehouse_id' => $store->id, 'status' => 'draft',
             'transfer_date' => now()->toDateString(), 'requested_by' => $mgr,
             'notes' => 'Restock showroom display']
        );
        $transfer->items()->delete();
        foreach ([['SKU-00001', 5], ['SKU-00005', 10]] as $i => [$sku, $qty]) {
            $pid = $productsBySku[$sku] ?? null;
            if ($pid) {
                $transfer->items()->create([
                    'company_id' => $cid, 'product_id' => $pid,
                    'name' => Product::withoutGlobalScopes()->find($pid)?->name ?? 'Item',
                    'quantity' => $qty, 'sort_order' => $i,
                ]);
            }
        }

        $this->command?->info("Seeded 2 warehouses, {$count} stock rows, 3 barcodes, 1 draft transfer.");
    }
}
