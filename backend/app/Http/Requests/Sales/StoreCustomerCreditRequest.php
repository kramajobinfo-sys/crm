<?php
namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff-issued credit note. System credits (overpayment/adjustment) never come through here. */
class StoreCustomerCreditRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['required', 'integer',
                Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'amount'   => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason'   => ['required', 'string', 'max:255'],
        ];
    }
}
