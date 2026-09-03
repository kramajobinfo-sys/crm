<?php
namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiKeyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'user_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('company_id', $this->user()->company_id)],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
