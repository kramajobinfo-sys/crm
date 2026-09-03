<?php
namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\DocumentLineRules;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    use DocumentLineRules;

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge($this->headerRules($companyId), $this->lineRules(false), [
            'deal_id'     => ['nullable','integer', Rule::exists('deals','id')->where('company_id',$companyId)],
            'status'      => ['nullable', Rule::in(Quotation::STATUSES)],
            'issue_date'  => ['nullable','date'],
            'valid_until' => ['nullable','date','after_or_equal:issue_date'],
        ]);
    }
}
