<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreProductRequest;
use App\Http\Requests\Sales\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:191',
            'category_id' => 'nullable|integer',
            'type' => 'nullable|string|in:goods,service',
            'status' => 'nullable|string|in:all,active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->products->paginate($filters, (int) ($filters['per_page'] ?? 25)), ProductResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->products->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'categories' => $this->products->categories()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
                'parent_id' => $c->parent_id, 'parent' => $c->parent?->name,
            ]),
            'tax_rates' => $this->products->taxRates()->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'rate' => (float) $r->rate,
                'is_inclusive' => (bool) $r->is_inclusive, 'is_default' => (bool) $r->is_default,
            ]),
            'types' => Product::TYPES,
            'next_sku' => $this->products->nextSku(),
            // For the Inventory product form: pick suppliers and the opening-stock warehouse.
            'vendors' => Vendor::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new ProductResource($this->products->find($id)));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return $this->success(new ProductResource($this->products->create($request->validated())), 'Product created', 201);
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        return $this->success(new ProductResource($this->products->update($product, $request->validated())), 'Product updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete();
        return $this->success(null, 'Product deleted');
    }

    // ---- categories & tax rates -----------------------------------------

    public function storeCategory(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'code' => ['required','string','max:32', Rule::unique('product_categories','code')->where('company_id',$companyId)],
            'parent_id' => ['nullable','integer', Rule::exists('product_categories','id')->where('company_id',$companyId)],
        ]);
        $cat = $this->products->createCategory($data);
        return $this->success(['id' => $cat->id, 'name' => $cat->name, 'code' => $cat->code], 'Category created', 201);
    }

    public function storeTaxRate(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:96',
            'code' => ['required','string','max:32', Rule::unique('tax_rates','code')->where('company_id',$companyId)],
            'rate' => 'required|numeric|min:0|max:100',
            'is_inclusive' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);
        $rate = $this->products->createTaxRate($data);
        return $this->success(['id' => $rate->id, 'name' => $rate->name, 'rate' => (float) $rate->rate], 'Tax rate created', 201);
    }
}
