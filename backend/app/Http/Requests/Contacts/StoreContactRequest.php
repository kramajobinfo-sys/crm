<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['required', 'integer',
                Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
