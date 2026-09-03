<?php
namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\DocumentLineRules;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    use DocumentLineRules;

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge($this->headerRules($companyId), $this->lineRules(false), [
            'status'        => ['nullable', Rule::in(SalesOrder::STATUSES)],
            'order_date'    => ['nullable','date'],
            'expected_date' => ['nullable','date'],
        ]);
    }
}
