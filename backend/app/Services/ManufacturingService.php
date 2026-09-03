<?php
namespace App\Services;

use App\Models\BomItem;
use App\Models\Build;
use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manufacturing / BOM (Phase B). Headerless BOM (component lines on the finished-good product) and
 * an immediate, atomic build that consumes components and produces the finished good through
 * InventoryService::applyMovement — the one row-locked ledger writer. See docs/MANUFACTURING_BOM_SCOPE.md.
 */
class ManufacturingService
{
    private const MAX_BOM_DEPTH = 20;

    public function __construct(private readonly InventoryService $inventory) {}

    // ---- BOM -------------------------------------------------------------

    /** Products that have a BOM (>=1 component line) — i.e. the manufacturable ones. */
    public function manufacturable(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Product::query()
            ->whereHas('bomItems')
            ->withCount('bomItems')
            ->with(['category:id,name'])
            ->search($f['q'] ?? null)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function bomFor(int $productId): array
    {
        $product = Product::findOrFail($productId);
        $items = BomItem::where('product_id', $productId)
            ->with('component:id,sku,name,unit,cost_price')
            ->orderBy('id')->get();
        return ['product' => $product, 'items' => $items];
    }

    /**
     * Replace a finished good's BOM. Rejects self-reference and any cycle (a component whose own
     * BOM transitively reaches the finished good).
     */
    public function setBom(int $productId, array $lines): array
    {
        $product = Product::findOrFail($productId);
        $companyId = $product->company_id;

        // Dedupe by component; validate before writing anything.
        $byComponent = [];
        foreach ($lines as $l) {
            $cid = (int) ($l['component_product_id'] ?? 0);
            $qty = (float) ($l['quantity'] ?? 0);
            if (!$cid || $qty <= 0) continue;
            if ($cid === $productId) {
                throw new RuntimeException('A product cannot be a component of itself.');
            }
            if ($this->componentTreeReaches($cid, $productId)) {
                $name = Product::withoutGlobalScope('company')->whereKey($cid)->value('name') ?? "#$cid";
                throw new RuntimeException("Adding \"{$name}\" would create a circular bill of materials.");
            }
            $byComponent[$cid] = $qty;   // last wins
        }

        DB::transaction(function () use ($product, $companyId, $byComponent) {
            BomItem::where('product_id', $product->id)->delete();
            foreach ($byComponent as $cid => $qty) {
                BomItem::create([
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'component_product_id' => $cid,
                    'quantity' => $qty,
                ]);
            }
        });

        return $this->bomFor($product->id);
    }

    /** DFS: does the BOM tree rooted at $componentId transitively reach $targetProductId? */
    private function componentTreeReaches(int $componentId, int $targetProductId, int $depth = 0): bool
    {
        if ($componentId === $targetProductId) return true;
        if ($depth >= self::MAX_BOM_DEPTH) {
            throw new RuntimeException('Bill of materials is nested too deeply.');
        }
        $children = BomItem::where('product_id', $componentId)->pluck('component_product_id');
        foreach ($children as $childId) {
            if ($this->componentTreeReaches((int) $childId, $targetProductId, $depth + 1)) return true;
        }
        return false;
    }

    // ---- availability ----------------------------------------------------

    /** How many finished units can be built at a warehouse, and which components are short. */
    public function availability(int $productId, int $warehouseId, float $quantity): array
    {
        $bom = BomItem::where('product_id', $productId)->with('component:id,name,cost_price')->get();
        if ($bom->isEmpty()) {
            return ['manufacturable' => false, 'buildable' => 0, 'shortfalls' => []];
        }

        $onHand = fn (int $cid) => (float) (StockItem::where('product_id', $cid)
            ->where('warehouse_id', $warehouseId)->value('quantity') ?? 0);

        $buildable = PHP_INT_MAX;
        $shortfalls = [];
        foreach ($bom as $line) {
            $perUnit = (float) $line->quantity;
            if ($perUnit <= 0) continue;
            $have = $onHand($line->component_product_id);
            $canMake = (int) floor($have / $perUnit);
            $buildable = min($buildable, $canMake);
            $required = $perUnit * $quantity;
            if ($have + 1e-9 < $required) {
                $shortfalls[] = ['component' => $line->component?->name, 'required' => round($required, 4), 'available' => round($have, 4)];
            }
        }

        return [
            'manufacturable' => true,
            'buildable' => $buildable === PHP_INT_MAX ? 0 : max(0, $buildable),
            'shortfalls' => $shortfalls,
        ];
    }

    // ---- build (immediate, atomic) --------------------------------------

    public function build(array $data): Build
    {
        $productId   = (int) $data['product_id'];
        $warehouseId = (int) $data['warehouse_id'];
        $qty         = (float) $data['quantity'];

        return DB::transaction(function () use ($productId, $warehouseId, $qty, $data) {
            $product = Product::findOrFail($productId);
            $bom = BomItem::where('product_id', $productId)->with('component:id,name,cost_price')->get();
            if ($bom->isEmpty()) {
                throw new RuntimeException('This product has no bill of materials, so it cannot be built.');
            }

            // Lock + check every component's on-hand up front; accumulate the rolled-up unit cost.
            $short = [];
            $unitCost = 0.0;
            $plan = [];
            foreach ($bom as $line) {
                $perUnit  = (float) $line->quantity;
                $required = round($perUnit * $qty, 4);
                $item = StockItem::where('product_id', $line->component_product_id)
                    ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
                $have = (float) ($item->quantity ?? 0);
                $cost = (float) (($item && $item->average_cost > 0) ? $item->average_cost : ($line->component->cost_price ?? 0));
                $unitCost += $cost * $perUnit;
                if ($have + 1e-9 < $required) {
                    $short[] = $line->component?->name.' (need '.rtrim(rtrim(number_format($required, 4, '.', ''), '0'), '.').', have '.rtrim(rtrim(number_format($have, 4, '.', ''), '0'), '.').')';
                }
                $plan[] = ['component_id' => $line->component_product_id, 'name' => $line->component?->name ?? 'Item', 'required' => $required, 'unit_cost' => round($cost, 2)];
            }
            if ($short) {
                throw new RuntimeException('Not enough stock to build: '.implode('; ', $short));
            }

            $unitCost = round($unitCost, 2);
            $buildNo = $this->nextBuildNo();

            $buildModel = Build::create([
                'company_id' => $product->company_id,
                'build_no' => $buildNo,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => round($unitCost * $qty, 2),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'built_by' => auth()->id(),
                'built_at' => now(),
            ]);

            // Consume components, then produce the finished good — all through the locked ledger.
            foreach ($plan as $p) {
                $this->inventory->applyMovement($p['component_id'], $warehouseId, 'consume', -$p['required'], [
                    'unit_cost' => $p['unit_cost'], 'reference' => $buildNo, 'note' => 'Build '.$buildNo,
                    'related_type' => Build::class, 'related_id' => $buildModel->id,
                ]);
                $buildModel->items()->create([
                    'company_id' => $product->company_id,
                    'component_product_id' => $p['component_id'],
                    'name' => $p['name'],
                    'quantity' => $p['required'],
                    'unit_cost' => $p['unit_cost'],
                ]);
            }
            $this->inventory->applyMovement($productId, $warehouseId, 'produce', abs($qty), [
                'unit_cost' => $unitCost, 'reference' => $buildNo, 'note' => 'Build '.$buildNo,
                'related_type' => Build::class, 'related_id' => $buildModel->id,
            ]);

            return $this->findBuild($buildModel->id);
        });
    }

    public function paginateBuilds(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Build::query()
            ->with(['product:id,sku,name', 'warehouse:id,name', 'builder:id,name'])
            ->when(!empty($f['product_id']), fn ($q) => $q->where('product_id', $f['product_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findBuild(int $id): Build
    {
        return Build::with([
            'product:id,sku,name', 'warehouse:id,name', 'builder:id,name',
            'items.component:id,sku,name',
        ])->findOrFail($id);
    }

    public function nextBuildNo(string $prefix = 'BUILD'): string
    {
        $companyId = auth()->user()?->company_id;
        $last = Build::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)->where('build_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(build_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('build_no');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }
}
