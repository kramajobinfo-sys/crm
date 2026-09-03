<?php
namespace App\Http\Requests\Manufacturing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'product_id'   => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'warehouse_id' => ['required','integer', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'quantity'     => ['required','numeric','gt:0','max:9999999'],
            'notes'        => ['nullable','string','max:500'],
        ];
    }
}
