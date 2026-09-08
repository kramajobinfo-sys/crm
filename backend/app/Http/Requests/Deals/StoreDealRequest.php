<?php
namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'deal_no' => ['nullable','string','max:32',
                Rule::unique('deals','deal_no')->where('company_id',$companyId)],
            'title'       => ['required','string','max:191'],
            'pipeline_id' => ['nullable','integer', Rule::exists('pipelines','id')->where('company_id',$companyId)],
            'stage_id'    => ['nullable','integer', Rule::exists('pipeline_stages','id')->where('company_id',$companyId)],
            'customer_id' => ['nullable','integer', Rule::exists('customers','id')->where('company_id',$companyId)],
            'lead_id'     => ['nullable','integer', Rule::exists('leads','id')->where('company_id',$companyId)],
            'owner_id'    => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'branch_id'   => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'amount'      => ['nullable','numeric','min:0','max:9999999999999'],
            'currency'    => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'probability' => ['nullable','integer','min:0','max:100'],
            'forecast_category' => ['nullable', Rule::in(\App\Models\Deal::FORECAST_CATEGORIES)],
            'expected_close_date' => ['nullable','date'],
            'source'      => ['nullable','string','max:96'],
            'competitor'  => ['nullable','string','max:191'],
            'notes'       => ['nullable','string','max:5000'],
            'custom_fields' => ['nullable', 'array'],
            'products'                 => ['nullable','array'],
            'products.*.product_id'    => ['nullable','integer'],
            'products.*.name'          => ['required_with:products','string','max:191'],
            'products.*.description'   => ['nullable','string','max:500'],
            'products.*.quantity'      => ['nullable','numeric','min:0'],
            'products.*.unit_price'    => ['nullable','numeric','min:0'],
            'products.*.discount_pct'  => ['nullable','numeric','min:0','max:100'],
            'contacts'                 => ['sometimes','nullable','array', function ($attribute, $value, $fail) {
                if (collect($value)->filter(fn ($row) => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))->count() > 1) {
                    $fail('Only one Deal Contact may be primary.');
                }
            }],
            'contacts.*.contact_id'    => ['required','integer','distinct', Rule::exists('contacts','id')->where('company_id',$companyId)->whereNull('deleted_at')],
            'contacts.*.role'          => ['required','string', Rule::in(\App\Models\Deal::CONTACT_ROLES)],
            'contacts.*.is_primary'    => ['nullable','boolean'],
        ];
    }
}
