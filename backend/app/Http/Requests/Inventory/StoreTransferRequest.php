<?php
namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'from_warehouse_id' => ['required','integer', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'to_warehouse_id'   => ['required','integer','different:from_warehouse_id', Rule::exists('warehouses','id')->where('company_id',$companyId)],
            'transfer_date'     => ['nullable','date'],
            'notes'             => ['nullable','string','max:2000'],
            'items'                => ['required','array','min:1'],
            'items.*.product_id'   => ['required','integer', Rule::exists('products','id')->where('company_id',$companyId)],
            'items.*.quantity'     => ['required','numeric','min:0.01'],
        ];
    }
}
