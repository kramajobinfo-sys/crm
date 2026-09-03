<?php
namespace App\Http\Requests\Purchase;

use App\Models\ApprovalWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'name'          => ['required','string','max:128'],
            'document_type' => ['required', Rule::in(ApprovalWorkflow::DOCUMENT_TYPES)],
            'min_amount'    => ['nullable','numeric','min:0'],
            'approver_ids'  => ['required','array','min:1'],
            'approver_ids.*'=> ['integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'is_active'     => ['nullable','boolean'],
        ];
    }
}
