<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_name' => ['required','string','max:191'],
            'name'         => ['required','string','max:191'],
            'email'        => ['required','email','max:191','unique:users,email'],
            'password'     => ['required','string','confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
