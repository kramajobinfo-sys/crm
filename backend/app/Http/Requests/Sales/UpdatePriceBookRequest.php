<?php
namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['sometimes','required','string','max:128'],
            'currency'    => ['sometimes','required','string','size:3', Rule::exists('currencies','code')],
            'description' => ['nullable','string','max:500'],
            'is_active'   => ['nullable','boolean'],
            'valid_from'  => ['nullable','date'],
            'valid_to'    => ['nullable','date','after_or_equal:valid_from'],
        ];
    }
}
