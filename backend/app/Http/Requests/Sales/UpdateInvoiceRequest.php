<?php
namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\DocumentLineRules;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    use DocumentLineRules;

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge($this->headerRules($companyId), $this->lineRules(false), [
            'status'     => ['nullable', Rule::in(Invoice::STATUSES)],
            'issue_date' => ['nullable','date'],
            'due_date'   => ['nullable','date','after_or_equal:issue_date'],
        ]);
    }
}
