<?php
namespace App\Http\Requests\Sales;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount'      => ['required','numeric','min:0.01','max:9999999999999'],
            'method'      => ['nullable', Rule::in(Payment::METHODS)],
            'received_at' => ['nullable','date'],
            'reference'   => ['nullable','string','max:96'],
            'notes'       => ['nullable','string','max:2000'],
        ];
    }
}
