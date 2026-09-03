<?php
namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\DocumentLineRules;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesOrderRequest extends FormRequest
{
    use DocumentLineRules;

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge($this->headerRules($companyId), $this->lineRules(true), [
            'quotation_id'  => ['nullable','integer', Rule::exists('quotations','id')->where('company_id',$companyId)],
            'deal_id'       => ['nullable','integer', Rule::exists('deals','id')->where('company_id',$companyId)],
            'status'        => ['nullable', Rule::in(SalesOrder::STATUSES)],
            'order_date'    => ['nullable','date'],
            'expected_date' => ['nullable','date'],
        ]);
    }
}
