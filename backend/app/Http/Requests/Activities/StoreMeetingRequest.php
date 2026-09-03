<?php
namespace App\Http\Requests\Activities;

use App\Models\Meeting;
use App\Http\Requests\Activities\Concerns\ActivityRelatedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    use ActivityRelatedRules;
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge([
            'title'        => ['required','string','max:191'],
            'description'  => ['nullable','string','max:5000'],
            'location'     => ['nullable','string','max:191'],
            'meeting_link' => ['nullable','url','max:500'],
            'status'       => ['nullable', Rule::in(Meeting::STATUSES)],
            'start_at'     => ['required','date'],
            'end_at'       => ['nullable','date','after_or_equal:start_at'],
            'participants'            => ['nullable','array'],
            'participants.*.user_id'  => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'participants.*.name'     => ['nullable','string','max:191'],
            'participants.*.email'    => ['nullable','email','max:191'],
            'participants.*.response' => ['nullable', Rule::in(['invited','accepted','declined','tentative'])],
        ], $this->relatedRules());
    }
}
