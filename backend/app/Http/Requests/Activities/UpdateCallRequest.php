<?php
namespace App\Http\Requests\Activities;

use App\Models\Call;
use App\Http\Requests\Activities\Concerns\ActivityRelatedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallRequest extends FormRequest
{
    use ActivityRelatedRules;
    public function rules(): array
    {
        return array_merge([
            'subject'          => ['sometimes','required','string','max:191'],
            'direction'        => ['nullable', Rule::in(Call::DIRECTIONS)],
            'status'           => ['nullable', Rule::in(Call::STATUSES)],
            'phone'            => ['nullable','string','max:32'],
            'duration_seconds' => ['nullable','integer','min:0','max:86400'],
            'notes'            => ['nullable','string','max:5000'],
            'scheduled_at'     => ['nullable','date'],
            'occurred_at'      => ['nullable','date'],
        ], $this->relatedRules());
    }
}
