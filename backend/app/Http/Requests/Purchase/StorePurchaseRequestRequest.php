<?php
namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'department_id' => ['nullable','integer', Rule::exists('departments','id')->where('company_id',$companyId)],
            'branch_id'     => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'needed_by'     => ['nullable','date'],
            'notes'         => ['nullable','string','max:5000'],
            'items'                    => ['required','array','min:1'],
            'items.*.product_id'       => ['nullable','integer'],
            'items.*.name'             => ['required','string','max:191'],
            'items.*.quantity'         => ['nullable','numeric','min:0'],
            'items.*.estimated_price'  => ['nullable','numeric','min:0'],
            'items.*.note'             => ['nullable','string','max:500'],
        ];
    }
}
