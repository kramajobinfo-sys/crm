<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePriceBookRequest;
use App\Http\Requests\Sales\UpdatePriceBookRequest;
use App\Http\Resources\PriceBookResource;
use App\Models\Currency;
use App\Models\PriceBook;
use App\Services\PriceBookService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class PriceBookController extends Controller
{
    public function __construct(private readonly PriceBookService $priceBooks) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:128',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|in:all,active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->priceBooks->paginate($f, (int) ($f['per_page'] ?? 25)), PriceBookResource::class);
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'currencies' => Currency::orderBy('code')->get(['code', 'name'])
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name]),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new PriceBookResource($this->priceBooks->find($id)));
    }

    public function store(StorePriceBookRequest $request): JsonResponse
    {
        return $this->success(new PriceBookResource($this->priceBooks->create($request->validated())), 'Price book created', 201);
    }

    public function update(UpdatePriceBookRequest $request, int $id): JsonResponse
    {
        $book = PriceBook::findOrFail($id);
        return $this->success(new PriceBookResource($this->priceBooks->update($book, $request->validated())), 'Price book updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->priceBooks->delete(PriceBook::findOrFail($id));
        return $this->success(null, 'Price book deleted');
    }

    /** Replace the whole set of product prices in a book. */
    public function syncEntries(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'entries' => ['present','array','max:2000'],
            'entries.*.product_id' => ['required','integer', Rule::exists('products','id')->where('company_id', $companyId)],
            'entries.*.unit_price' => ['required','numeric','min:0','max:9999999999999'],
        ]);
        $book = PriceBook::findOrFail($id);
        return $this->success(new PriceBookResource($this->priceBooks->syncEntries($book, $data['entries'])), 'Prices updated');
    }

    /** Suggest unit prices for a set of products against the effective book (read/convenience). */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_book_id' => 'nullable|integer',
            'customer_id'   => 'nullable|integer',
            'currency'      => 'nullable|string|size:3',
            'product_ids'   => 'required|array|min:1|max:200',
            'product_ids.*' => 'integer',
        ]);
        try {
            $result = $this->priceBooks->resolve($data);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success($result);
    }
}
