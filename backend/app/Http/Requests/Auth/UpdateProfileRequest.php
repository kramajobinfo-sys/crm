<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes','required','string','max:191'],
            'phone'    => ['nullable','string','max:32'],
            'language' => ['nullable','string','in:en,ar'],
            'timezone' => ['nullable','string','max:64'],
        ];
    }
}
