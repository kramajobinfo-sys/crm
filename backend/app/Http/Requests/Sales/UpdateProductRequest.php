<?php
namespace App\Http\Requests\Sales;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id = (int) $this->route('id');
        return [
            'sku' => ['sometimes','string','max:64',
                Rule::unique('products','sku')->where('company_id',$companyId)->ignore($id)],
            'name'        => ['sometimes','required','string','max:191'],
            'description' => ['nullable','string','max:5000'],
            'category_id' => ['nullable','integer', Rule::exists('product_categories','id')->where('company_id',$companyId)],
            'type'        => ['nullable', Rule::in(Product::TYPES)],
            'unit'        => ['nullable','string','max:24'],
            'cost_price'  => ['nullable','numeric','min:0'],
            'sale_price'  => ['nullable','numeric','min:0'],
            'tax_rate_id' => ['nullable','integer', Rule::exists('tax_rates','id')->where('company_id',$companyId)],
            'barcode'     => ['nullable','string','max:64'],
            'track_inventory' => ['nullable','boolean'],
            'reorder_level'   => ['nullable','numeric','min:0'],
            'is_active'   => ['nullable','boolean'],

            // Suppliers — sending the array replaces the whole set.
            'suppliers'                  => ['nullable','array','max:20'],
            'suppliers.*.vendor_id'      => ['required_with:suppliers','integer', Rule::exists('vendors','id')->where('company_id',$companyId)],
            'suppliers.*.supplier_sku'   => ['nullable','string','max:64'],
            'suppliers.*.cost'           => ['nullable','numeric','min:0'],
            'suppliers.*.lead_time_days' => ['nullable','integer','min:0','max:3650'],
            'suppliers.*.currency'       => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'suppliers.*.is_preferred'   => ['nullable','boolean'],
        ];
    }
}
