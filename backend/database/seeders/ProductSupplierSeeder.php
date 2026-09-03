<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo product-supplier links (Inventory product enrichment). Idempotent; company_id explicit.
 */
class ProductSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $vendors = Vendor::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->take(2)->pluck('id')->all();
        if (!$vendors) return;

        $products = Product::withoutGlobalScopes()->where('company_id', $cid)
            ->where('type', 'goods')->orderBy('id')->take(4)->get();

        foreach ($products as $i => $p) {
            // Preferred vendor for each; a couple also get a secondary supplier.
            ProductSupplier::updateOrCreate(
                ['product_id' => $p->id, 'vendor_id' => $vendors[0]],
                ['company_id' => $cid, 'supplier_sku' => 'V1-'.$p->sku, 'cost' => round((float) $p->cost_price * 0.98, 2),
                 'lead_time_days' => 7, 'is_preferred' => true]
            );
            if (isset($vendors[1]) && $i % 2 === 0) {
                ProductSupplier::updateOrCreate(
                    ['product_id' => $p->id, 'vendor_id' => $vendors[1]],
                    ['company_id' => $cid, 'supplier_sku' => 'V2-'.$p->sku, 'cost' => round((float) $p->cost_price * 1.03, 2),
                     'lead_time_days' => 14, 'is_preferred' => false]
                );
            }
        }
    }
}
