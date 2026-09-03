<?php
namespace App\Http\Requests\Marketing;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'name'    => ['required','string','max:191'],
            'type'    => ['required', Rule::in(Campaign::TYPES)],
            'subject' => ['nullable','string','max:255','required_if:type,email'],
            'body'    => ['required','string','max:100000'],
            'email_template_id' => ['nullable','integer', Rule::exists('email_templates','id')->where('company_id',$companyId)],
            'email_account_id'  => ['nullable','integer', Rule::exists('email_accounts','id')->where('company_id',$companyId)],
            'sms_provider_id'   => ['nullable','integer', Rule::exists('sms_providers','id')->where('company_id',$companyId)],
            'audience'          => ['required','array'],
            'audience.source'   => ['required','string','in:customers,leads'],
            'audience.filters'  => ['nullable','array'],
            'scheduled_at'      => ['nullable','date'],
        ];
    }
}
