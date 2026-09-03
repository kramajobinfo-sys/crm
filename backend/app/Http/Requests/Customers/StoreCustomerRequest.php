<?php
namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            // Optional: the service generates one when omitted. Uniqueness is per company.
            'customer_no' => ['nullable','string','max:32',
                Rule::unique('customers', 'customer_no')->where('company_id', $companyId)],
            'type'   => ['required', Rule::in(Customer::TYPES)],
            'status' => ['nullable', Rule::in(Customer::STATUSES)],
            'name'       => ['required','string','max:191'],
            'legal_name' => ['nullable','string','max:191'],
            'group_id'   => ['nullable','integer', Rule::exists('customer_groups','id')->where('company_id',$companyId)],
            'owner_id'   => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'branch_id'  => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'email'   => ['nullable','email','max:191'],
            'phone'   => ['nullable','string','max:32'],
            'mobile'  => ['nullable','string','max:32'],
            'website' => ['nullable','url','max:191'],
            'tax_id'  => ['nullable','string','max:64'],
            'currency' => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'price_book_id' => ['nullable','integer', Rule::exists('price_books','id')->where('company_id',$companyId)],
            'credit_limit'       => ['nullable','numeric','min:0','max:9999999999999'],
            'payment_terms_days' => ['nullable','integer','min:0','max:365'],
            'notes' => ['nullable','string','max:5000'],

            'addresses'               => ['nullable','array','max:10'],
            'addresses.*.type'        => ['nullable', Rule::in(\App\Models\Address::TYPES)],
            'addresses.*.label'       => ['nullable','string','max:64'],
            'addresses.*.line1'       => ['required_with:addresses.*','string','max:191'],
            'addresses.*.line2'       => ['nullable','string','max:191'],
            'addresses.*.city'        => ['nullable','string','max:96'],
            'addresses.*.state'       => ['nullable','string','max:96'],
            'addresses.*.postal_code' => ['nullable','string','max:32'],
            'addresses.*.country'     => ['nullable','string','max:96'],
            'addresses.*.is_default'  => ['nullable','boolean'],
        ];
    }
}
