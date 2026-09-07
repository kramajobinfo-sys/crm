<?php
namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'lead_no' => ['nullable','string','max:32',
                Rule::unique('leads','lead_no')->where('company_id',$companyId)],
            'name'         => ['required','string','max:191'],
            'company_name' => ['nullable','string','max:191'],
            'title'        => ['nullable','string','max:128'],
            'email'  => ['nullable','email','max:191'],
            'phone'  => ['nullable','string','max:32'],
            'mobile' => ['nullable','string','max:32'],
            'website'=> ['nullable','url','max:191'],
            'source_id' => ['nullable','integer', Rule::exists('lead_sources','id')->where('company_id',$companyId)],
            'status_id' => ['nullable','integer', Rule::exists('lead_statuses','id')->where('company_id',$companyId)],
            'lost_reason_id' => ['nullable','integer', Rule::exists('lost_reasons','id')->where('company_id',$companyId)],
            'priority' => ['nullable', Rule::in(['low','medium','high','urgent'])],
            'owner_id'  => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'branch_id' => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'estimated_value' => ['nullable','numeric','min:0','max:9999999999999'],
            'currency' => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'expected_close_date' => ['nullable','date'],
            'follow_up_at' => ['nullable','date'],
            'next_action' => ['nullable','string','max:191'],
            'notes' => ['nullable','string','max:5000'],
        ];
    }
}
