<?php
namespace App\Http\Requests\Purchase;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $id = (int) $this->route('id');
        return [
            'vendor_no' => ['nullable','string','max:32',
                Rule::unique('vendors','vendor_no')->where('company_id',$companyId)->ignore($id)],
            'name'        => ['required','string','max:191'],
            'legal_name'  => ['nullable','string','max:191'],
            'email'       => ['nullable','email','max:191'],
            'phone'       => ['nullable','string','max:32'],
            'mobile'      => ['nullable','string','max:32'],
            'website'     => ['nullable','url','max:191'],
            'tax_id'      => ['nullable','string','max:64'],
            'currency'    => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'payment_terms_days' => ['nullable','integer','min:0','max:365'],
            'address'      => ['nullable','string','max:500'],
            'contact_name' => ['nullable','string','max:128'],
            'status'       => ['nullable', Rule::in(Vendor::STATUSES)],
            'notes'        => ['nullable','string','max:5000'],
        ];
    }
}
