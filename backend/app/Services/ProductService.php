<?php
namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private readonly InventoryService $inventory) {}

    private const DETAIL = [
        'category:id,name,parent_id', 'taxRate:id,name,rate',
        'suppliers.vendor:id,name', 'preferredSupplier.vendor:id,name',
    ];

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category:id,name', 'taxRate:id,name,rate', 'preferredSupplier.vendor:id,name'])
            ->search($filters['q'] ?? null)
            ->when(!empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(!empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): Product
    {
        return Product::with(self::DETAIL)->findOrFail($id);
    }

    /**
     * Create a product and, in the SAME transaction: sync its suppliers and (optionally) seed
     * opening stock. Opening stock funnels through InventoryService::receive — the one row-locked
     * ledger writer — so a failed movement rolls the whole thing back (no phantom stock). Opening
     * stock is skipped for services (track_inventory=false have no stock rows).
     */
    public function create(array $data): Product
    {
        $suppliers = $data['suppliers'] ?? null;
        $opening   = $data['opening_stock'] ?? null;
        unset($data['suppliers'], $data['opening_stock']);
        $data['sku'] ??= $this->nextSku();

        return DB::transaction(function () use ($data, $suppliers, $opening) {
            $product = Product::create($data);
            if (is_array($suppliers)) $this->syncSuppliers($product, $suppliers);

            if ($product->track_inventory && is_array($opening)
                && !empty($opening['warehouse_id']) && (float) ($opening['quantity'] ?? 0) > 0) {
                $this->inventory->receive(
                    $product->id, (int) $opening['warehouse_id'], (float) $opening['quantity'],
                    ['type' => 'receipt', 'unit_cost' => (float) ($opening['unit_cost'] ?? $product->cost_price ?? 0),
                     'note' => 'Opening stock']
                );
                if (!empty($opening['bin_location'])) {
                    \App\Models\StockItem::where('product_id', $product->id)
                        ->where('warehouse_id', $opening['warehouse_id'])
                        ->update(['bin_location' => $opening['bin_location']]);
                }
            }

            return $this->find($product->id);
        });
    }

    public function update(Product $product, array $data): Product
    {
        $suppliers = $data['suppliers'] ?? null;
        unset($data['suppliers'], $data['opening_stock']);   // opening_stock is create-only

        return DB::transaction(function () use ($product, $data, $suppliers) {
            $product->update($data);
            if (is_array($suppliers)) $this->syncSuppliers($product, $suppliers);
            return $this->find($product->id);
        });
    }

    /** Replace a product's supplier set. Deduped by vendor_id; at most one is_preferred. */
    private function syncSuppliers(Product $product, array $suppliers): void
    {
        $product->suppliers()->delete();
        $preferredSeen = false;
        $rows = [];
        foreach ($suppliers as $s) {
            $vid = (int) ($s['vendor_id'] ?? 0);
            if (!$vid) continue;
            $isPreferred = !$preferredSeen && !empty($s['is_preferred']);
            if ($isPreferred) $preferredSeen = true;
            $rows[$vid] = [                                  // key by vendor => dedupe, last wins
                'company_id'     => $product->company_id,
                'product_id'     => $product->id,
                'vendor_id'      => $vid,
                'supplier_sku'   => $s['supplier_sku'] ?? null,
                'cost'           => round((float) ($s['cost'] ?? 0), 2),
                'lead_time_days' => $s['lead_time_days'] ?? null,
                'currency'       => $s['currency'] ?? null,
                'is_preferred'   => $isPreferred,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        if ($rows) $product->suppliers()->insert(array_values($rows));
    }

    public function stats(): array
    {
        return [
            'total'    => Product::count(),
            'active'   => Product::where('is_active', true)->count(),
            'goods'    => Product::where('type', 'goods')->count(),
            'services' => Product::where('type', 'service')->count(),
        ];
    }

    /** Sequential per-company SKU, e.g. SKU-00042. */
    public function nextSku(string $prefix = 'SKU'): string
    {
        $companyId = auth()->user()?->company_id;
        $last = Product::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)
            ->where('sku', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(sku, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('sku');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }

    // ---- categories & tax rates -----------------------------------------

    public function categories(): \Illuminate\Support\Collection
    {
        return ProductCategory::with('parent:id,name')->orderBy('name')->get();
    }

    public function createCategory(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function taxRates(): \Illuminate\Support\Collection
    {
        return TaxRate::orderByDesc('is_default')->orderBy('name')->get();
    }

    public function createTaxRate(array $data): TaxRate
    {
        // Only one default rate per company.
        if (!empty($data['is_default'])) {
            TaxRate::where('is_default', true)->update(['is_default' => false]);
        }
        return TaxRate::create($data);
    }
}
