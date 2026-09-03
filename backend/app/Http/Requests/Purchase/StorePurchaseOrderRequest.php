<?php
namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'vendor_id'     => ['required','integer', Rule::exists('vendors','id')->where('company_id',$companyId)],
            'warehouse_id'  => ['nullable','integer', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'order_date'    => ['nullable','date'],
            'expected_date' => ['nullable','date'],
            'currency'      => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'notes'         => ['nullable','string','max:5000'],
            'terms'         => ['nullable','string','max:5000'],
            'items'                    => ['required','array','min:1'],
            'items.*.product_id'       => ['nullable','integer'],
            'items.*.name'             => ['required','string','max:191'],
            'items.*.description'      => ['nullable','string','max:500'],
            'items.*.quantity'         => ['nullable','numeric','min:0'],
            'items.*.unit_price'       => ['nullable','numeric','min:0'],
            'items.*.discount_pct'     => ['nullable','numeric','min:0','max:100'],
            'items.*.tax_rate_id'      => ['nullable','integer','exists:tax_rates,id'],
        ];
    }
}
