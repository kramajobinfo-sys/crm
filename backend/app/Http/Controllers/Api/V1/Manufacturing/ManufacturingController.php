<?php
namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturing\BuildRequest;
use App\Http\Requests\Manufacturing\SetBomRequest;
use App\Http\Resources\BuildResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ManufacturingController extends Controller
{
    public function __construct(private readonly ManufacturingService $mfg) {}

    public function boms(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => 'nullable|string|max:191', 'per_page' => 'nullable|integer|min:1|max:100']);
        $page = $this->mfg->manufacturable($f, (int) ($f['per_page'] ?? 25));
        return $this->paginated($page->setCollection($page->getCollection()->map(fn ($p) => [
            'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name,
            'category' => $p->category?->name, 'components_count' => $p->bom_items_count,
        ])), null);
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'type', 'cost_price']),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function getBom(int $id): JsonResponse
    {
        $bom = $this->mfg->bomFor($id);
        return $this->success($this->presentBom($bom));
    }

    public function setBom(SetBomRequest $request, int $id): JsonResponse
    {
        try {
            $bom = $this->mfg->setBom($id, $request->validated()['lines']);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success($this->presentBom($bom), 'Bill of materials saved');
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'warehouse_id' => ['required','integer', \Illuminate\Validation\Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'quantity' => 'required|numeric|gt:0',
        ]);
        return $this->success($this->mfg->availability($id, (int) $data['warehouse_id'], (float) $data['quantity']));
    }

    public function builds(Request $request): JsonResponse
    {
        $f = $request->validate(['product_id' => 'nullable|integer', 'per_page' => 'nullable|integer|min:1|max:100']);
        return $this->paginated($this->mfg->paginateBuilds($f, (int) ($f['per_page'] ?? 25)), BuildResource::class);
    }

    public function showBuild(int $id): JsonResponse
    {
        return $this->success(new BuildResource($this->mfg->findBuild($id)));
    }

    public function storeBuild(BuildRequest $request): JsonResponse
    {
        try {
            $build = $this->mfg->build($request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new BuildResource($build), 'Build completed', 201);
    }

    private function presentBom(array $bom): array
    {
        return [
            'product' => ['id' => $bom['product']->id, 'sku' => $bom['product']->sku, 'name' => $bom['product']->name],
            'items' => $bom['items']->map(fn ($it) => [
                'id' => $it->id,
                'component_product_id' => $it->component_product_id,
                'quantity' => (float) $it->quantity,
                'component' => $it->component ? [
                    'id' => $it->component->id, 'sku' => $it->component->sku, 'name' => $it->component->name,
                    'unit' => $it->component->unit, 'cost_price' => (float) $it->component->cost_price,
                ] : null,
            ]),
        ];
    }
}
