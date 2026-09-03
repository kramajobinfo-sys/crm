<?php
namespace App\Http\Requests\Sales\Concerns;

/** Shared validation for the `items[]` array carried by quotations/orders/invoices. */
trait DocumentLineRules
{
    protected function lineRules(bool $required = false): array
    {
        return [
            'items'                => [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : ''],
            'items.*.product_id'   => ['nullable','integer'],
            'items.*.name'         => ['required_with:items','string','max:191'],
            'items.*.description'  => ['nullable','string','max:500'],
            'items.*.quantity'     => ['nullable','numeric','min:0'],
            'items.*.unit_price'   => ['nullable','numeric','min:0'],
            'items.*.discount_pct' => ['nullable','numeric','min:0','max:100'],
            'items.*.tax_rate_id'  => ['nullable','integer','exists:tax_rates,id'],
        ];
    }

    protected function headerRules(int $companyId): array
    {
        return [
            'customer_id' => ['nullable','integer', \Illuminate\Validation\Rule::exists('customers','id')->where('company_id',$companyId)],
            'owner_id'    => ['nullable','integer', \Illuminate\Validation\Rule::exists('users','id')->where('company_id',$companyId)],
            'branch_id'   => ['nullable','integer', \Illuminate\Validation\Rule::exists('branches','id')->where('company_id',$companyId)],
            'currency'    => ['nullable','string','size:3', \Illuminate\Validation\Rule::exists('currencies','code')],
            'notes'       => ['nullable','string','max:5000'],
            'terms'       => ['nullable','string','max:5000'],
        ];
    }
}
