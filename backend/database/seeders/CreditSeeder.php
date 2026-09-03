<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo customer credits.
 *
 * Only `manual` credits are seeded. The other two sources (`overpayment`,
 * `invoice_adjustment`) are minted by SalesService as a side effect of real money movement,
 * and faking them here would create credit rows with no payment behind them — exactly the
 * orphan state deletePayment exists to prevent.
 *
 * Idempotent via updateOrCreate on (company_id, credit_no), and every write passes
 * company_id explicitly: BelongsToCompany's global scope does not fire unauthenticated.
 */
class CreditSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $accountant = User::withoutGlobalScope('company')
            ->where('company_id', $cid)->where('email', 'accounts@krama.local')->value('id');

        $customers = Customer::withoutGlobalScope('company')
            ->where('company_id', $cid)->orderBy('id')->limit(2)->get(['id', 'currency']);
        if ($customers->isEmpty()) return;

        $rows = [
            ['CR-90001', 0, 250.00, 'Goodwill credit for a delayed delivery.'],
            ['CR-90002', 1, 75.50,  'Returned item — restocking credit.'],
        ];

        foreach ($rows as [$no, $idx, $amount, $reason]) {
            $customer = $customers[$idx] ?? $customers[0];

            CustomerCredit::updateOrCreate(
                ['company_id' => $cid, 'credit_no' => $no],
                [
                    'customer_id' => $customer->id,
                    'source' => 'manual',
                    'currency' => $customer->currency ?: 'USD',
                    'amount' => $amount,
                    'applied_amount' => 0,
                    'status' => 'open',
                    'reason' => $reason,
                    'created_by' => $accountant,
                    'issued_at' => now(),
                ]
            );
        }
    }
}
