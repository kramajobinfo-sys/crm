<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\PriceBook;
use App\Models\PriceBookEntry;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo data for Price Books (Zoho gap #7). Idempotent; company_id passed explicitly since
 * BelongsToCompany's scope does not fire in an unauthenticated seeder context.
 */
class PriceBookSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        // A wholesale book in the base currency (USD), ~12% off list on a handful of products.
        $wholesale = PriceBook::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Wholesale (USD)'],
            ['currency' => 'USD', 'description' => 'Bulk / reseller pricing', 'is_active' => true]
        );

        $products = Product::withoutGlobalScopes()->where('company_id', $cid)
            ->orderBy('id')->take(5)->get(['id', 'sale_price']);
        foreach ($products as $p) {
            PriceBookEntry::updateOrCreate(
                ['price_book_id' => $wholesale->id, 'product_id' => $p->id],
                ['company_id' => $cid, 'unit_price' => round((float) $p->sale_price * 0.88, 2)]
            );
        }

        // An empty EUR book, to exercise the per-currency / currency-mismatch paths.
        PriceBook::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Retail (EUR)'],
            ['currency' => 'EUR', 'description' => 'Eurozone list pricing', 'is_active' => true]
        );

        // Attach wholesale to the first customer group if one exists, else to the first customer.
        $group = CustomerGroup::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();
        if ($group && !$group->price_book_id) {
            $group->forceFill(['price_book_id' => $wholesale->id])->save();
        } elseif (!$group) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();
            if ($customer && !$customer->price_book_id) {
                $customer->forceFill(['price_book_id' => $wholesale->id])->save();
            }
        }
    }
}
