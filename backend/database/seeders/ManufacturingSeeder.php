<?php
namespace Database\Seeders;

use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo bill of materials (Zoho gap — Manufacturing/BOM, Phase B). Defines an assembled "bundle"
 * finished good from existing catalogue products. No build is performed (that mutates stock);
 * users build via the UI. Idempotent; company_id explicit.
 */
class ManufacturingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $bySku = fn (string $sku) => Product::withoutGlobalScopes()->where('company_id', $cid)->where('sku', $sku)->first();

        // A finished good assembled from other products.
        $bundle = Product::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $cid, 'sku' => 'KIT-OFFICE-01'],
            ['name' => 'Office starter bundle', 'type' => 'goods', 'unit' => 'set',
             'cost_price' => 0, 'sale_price' => 6200, 'track_inventory' => true, 'is_active' => true]
        );

        $components = [
            ['SKU-00001', 1],   // Executive desk
            ['SKU-00002', 1],   // Ergonomic office chair
            ['SKU-00005', 2],   // Accent floor lamp
        ];
        foreach ($components as [$sku, $qty]) {
            $c = $bySku($sku);
            if (!$c) continue;
            BomItem::updateOrCreate(
                ['product_id' => $bundle->id, 'component_product_id' => $c->id],
                ['company_id' => $cid, 'quantity' => $qty]
            );
        }
    }
}
