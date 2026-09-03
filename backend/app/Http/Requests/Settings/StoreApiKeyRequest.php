<?php
namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $this->user()->company_id)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
