<?php
namespace App\Services\Dynamics\Syncers;

use App\Models\Customer;

/**
 * BC `customers` -> CRM `customers` (pull only).
 *
 * Only fields BC is authoritative for are written. `owner_id`, `branch_id`, `group_id`,
 * `price_book_id` and `credit_limit` are deliberately left alone: they are CRM-side
 * organisational choices with no BC equivalent, and guessing them would overwrite decisions a
 * user made in Krama on every sync.
 */
class CustomerSyncer extends PullSyncer
{
    public function crmEntity(): string { return 'customer'; }
    public function bcEntity(): string  { return 'customers'; }

    protected function businessKey(): string { return 'customer_no'; }
    protected function modelClass(): string  { return Customer::class; }

    protected function defaultFieldMap(): array
    {
        return [
            'customer_no' => 'number',
            'name'        => 'displayName',
            'email'       => 'email',
            'phone'       => 'phoneNumber',
            'website'     => 'website',
            'tax_id'      => 'taxRegistrationNumber',
            'currency'    => 'currencyCode',
            'status'      => 'blocked',
        ];
    }

    protected function toAttributes(array $row, array $map, int $companyId, string $key): array
    {
        $get = fn (string $crmField) => $this->stringOrNull($row[$map[$crmField]] ?? null);

        return [
            'company_id'  => $companyId,
            'customer_no' => $key,
            // BC's displayName is the account name; fall back to the number so `name`
            // (NOT NULL) can never be written empty.
            'name'        => $get('name') ?? $key,
            'email'       => $get('email'),
            'phone'       => $get('phone'),
            'website'     => $get('website'),
            'tax_id'      => $get('tax_id'),
            // BC currency codes are 3 letters; an empty code means the BC company default,
            // which we cannot resolve here, so leave the CRM default in place.
            'currency'    => $get('currency'),
            // BC `blocked` is an enum-ish string ('', 'Ship', 'Invoice', 'All'). Anything
            // non-empty means the account is restricted somewhere, which maps to blocked.
            'status'      => $get('status') === null ? 'active' : 'blocked',
            'type'        => 'company',
        ];
    }
}
