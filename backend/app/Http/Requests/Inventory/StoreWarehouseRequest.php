<?php
namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id = (int) $this->route('id');
        return [
            'name' => ['required','string','max:128'],
            'code' => ['required','string','max:32',
                Rule::unique('warehouses','code')->where('company_id',$companyId)->ignore($id)],
            'branch_id'     => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'address'       => ['nullable','string','max:500'],
            'contact_name'  => ['nullable','string','max:128'],
            'contact_phone' => ['nullable','string','max:32'],
            'is_default'    => ['nullable','boolean'],
            'is_active'     => ['nullable','boolean'],
        ];
    }
}
