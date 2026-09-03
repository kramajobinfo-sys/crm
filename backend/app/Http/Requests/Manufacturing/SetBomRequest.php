<?php
namespace App\Http\Requests\Manufacturing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetBomRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'lines' => ['present','array','max:200'],
            'lines.*.component_product_id' => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'lines.*.quantity' => ['required','numeric','gt:0'],
        ];
    }
}
