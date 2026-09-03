<?php
namespace App\Http\Requests\Forecasts;

use App\Models\SalesTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetTargetsRequest extends FormRequest
{
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'period_type'  => ['required', Rule::in(SalesTarget::PERIOD_TYPES)],
            'period_start' => ['required', 'date'],
            'targets'                  => ['present', 'array', 'max:500'],
            'targets.*.user_id'        => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'targets.*.target_amount'  => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
