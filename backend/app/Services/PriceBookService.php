<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\PriceBook;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Price Books (Zoho gap #7) — convenience / suggest-only. This service resolves a *suggested*
 * unit_price the sales line editor pre-fills; it never writes prices onto documents (the
 * quote/order/invoice write paths in SalesService are unchanged). See docs/PRICE_BOOKS_SCOPE.md.
 */
class PriceBookService
{
    // ---- CRUD ------------------------------------------------------------

    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return PriceBook::query()
            ->withCount('entries')
            ->search($f['q'] ?? null)
            ->when(!empty($f['currency']), fn ($q) => $q->where('currency', strtoupper($f['currency'])))
            ->when(($f['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($f['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): PriceBook
    {
        return PriceBook::with(['entries' => fn ($q) => $q->with('product:id,sku,name,sale_price')->orderBy('id')])
            ->withCount('entries')
            ->findOrFail($id);
    }

    public function create(array $data): PriceBook
    {
        $data['currency'] = strtoupper($data['currency']);
        return $this->find(PriceBook::create($data)->id);
    }

    public function update(PriceBook $book, array $data): PriceBook
    {
        if (isset($data['currency'])) $data['currency'] = strtoupper($data['currency']);
        $book->update($data);
        return $this->find($book->id);
    }

    public function delete(PriceBook $book): void
    {
        $book->delete();
    }

    /**
     * Replace a book's entries wholesale. Absolute unit_price per product (decision: not
     * discount-off-list). Deduped by product_id — the last entry for a product wins.
     */
    public function syncEntries(PriceBook $book, array $entries): PriceBook
    {
        return DB::transaction(function () use ($book, $entries) {
            $book->entries()->delete();

            $rows = [];
            foreach ($entries as $e) {
                $pid = (int) ($e['product_id'] ?? 0);
                if (!$pid) continue;
                $rows[$pid] = [                       // keyed by pid => dedupe, last wins
                    'company_id'    => $book->company_id,
                    'price_book_id' => $book->id,
                    'product_id'    => $pid,
                    'unit_price'    => round((float) ($e['unit_price'] ?? 0), 2),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
            if ($rows) $book->entries()->insert(array_values($rows));

            return $this->find($book->id);
        });
    }

    // ---- Resolver (the convenience read path the line editor calls) ------

    /**
     * Resolve suggested unit prices for a set of products against the effective price book.
     *
     * Precedence for the effective book: an explicit price_book_id → the customer's own book →
     * the customer's group book (mirrors Customer::effectivePriceBookId). A product with no entry
     * in the book falls through to products.sale_price (declared to be in the company base
     * currency). All lookups are batched — no per-product query.
     *
     * Currency rule (never a silent mismatch): if an *explicitly chosen* book's currency differs
     * from the requested document currency, that's a hard 422 — pricing a EUR document from a USD
     * book is always a mistake. An *implicitly* resolved (customer/group) book that mismatches is
     * not fatal: it's reported back as `currency_mismatch` and the prices fall through to list, so
     * the editor can surface it rather than silently applying the wrong-currency book.
     */
    public function resolve(array $params): array
    {
        $productIds = array_values(array_unique(array_map('intval', $params['product_ids'] ?? [])));
        $currency   = isset($params['currency']) ? strtoupper($params['currency']) : null;

        [$book, $mismatch] = $this->resolveEffectiveBook($params, $currency);

        // Fallback list prices for every requested product, in one query.
        $salePrices = $productIds
            ? Product::withTrashed()->whereIn('id', $productIds)->pluck('sale_price', 'id')
            : collect();

        // Book entry prices, in one query.
        $bookPrices = $book && $productIds
            ? $book->entries()->whereIn('product_id', $productIds)->pluck('unit_price', 'product_id')
            : collect();

        $prices = [];
        $source = [];
        foreach ($productIds as $pid) {
            if ($book && $bookPrices->has($pid)) {
                $prices[$pid] = (float) $bookPrices[$pid];
                $source[$pid] = 'price_book';
            } else {
                $prices[$pid] = (float) ($salePrices[$pid] ?? 0);
                $source[$pid] = 'list';
            }
        }

        return [
            'price_book' => $book ? ['id' => $book->id, 'name' => $book->name, 'currency' => $book->currency] : null,
            'currency'   => $currency,
            'prices'     => $prices,
            'source'     => $source,
            'currency_mismatch' => $mismatch,
        ];
    }

    /**
     * @return array{0: ?PriceBook, 1: ?array}  [effective book (null = use list prices), mismatch info]
     */
    private function resolveEffectiveBook(array $params, ?string $currency): array
    {
        // 1) Explicit book — must belong to the company (global scope handles that); a currency
        //    mismatch here is a hard error.
        if (!empty($params['price_book_id'])) {
            $book = PriceBook::active()->findOrFail((int) $params['price_book_id']);
            if ($currency && $book->currency !== $currency) {
                throw new RuntimeException(
                    "Price book \"{$book->name}\" is priced in {$book->currency}; this document is in {$currency}."
                );
            }
            return [$book, null];
        }

        // 2) Implicit — from the customer's own book, else the group's.
        if (!empty($params['customer_id'])) {
            $customer = Customer::with('group:id,price_book_id')->find((int) $params['customer_id']);
            $bookId = $customer?->effectivePriceBookId();
            if ($bookId) {
                $book = PriceBook::active()->find($bookId);
                if ($book && $currency && $book->currency !== $currency) {
                    // Reported, not fatal — fall through to list prices.
                    return [null, [
                        'price_book_id' => $book->id, 'name' => $book->name, 'currency' => $book->currency,
                    ]];
                }
                return [$book, null];
            }
        }

        return [null, null];
    }
}
