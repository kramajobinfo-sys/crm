<?php
namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'email_account_id' => ['nullable','integer', Rule::exists('email_accounts','id')->where('company_id',$companyId)],
            'to'          => ['required','array','min:1'],
            'to.*'        => ['email'],
            'cc'          => ['nullable','array'],
            'cc.*'        => ['email'],
            'bcc'         => ['nullable','array'],
            'bcc.*'       => ['email'],
            'subject'     => ['required','string','max:255'],
            'body_html'   => ['nullable','string','max:100000'],
            'template_id' => ['nullable','integer', Rule::exists('email_templates','id')->where('company_id',$companyId)],
            'related_type'=> ['nullable','string', Rule::in(['deal','lead','customer'])],
            'related_id'  => ['nullable','integer','required_with:related_type'],
            'send'        => ['nullable','boolean'],
        ];
    }
}
