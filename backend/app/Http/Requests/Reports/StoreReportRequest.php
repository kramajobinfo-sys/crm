<?php
namespace App\Http\Requests\Reports;

use App\Models\SavedReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required','string','max:191'],
            'description' => ['nullable','string','max:500'],
            'dataset' => ['required','string','max:48'],
            'dimension' => ['nullable','string','max:48'],
            'measures' => ['nullable','array'],
            'measures.*' => ['string','max:48'],
            'filters' => ['nullable','array'],
            'chart_type' => ['nullable', Rule::in(SavedReport::CHART_TYPES)],
            'is_shared' => ['nullable','boolean'],
        ];
    }
}
