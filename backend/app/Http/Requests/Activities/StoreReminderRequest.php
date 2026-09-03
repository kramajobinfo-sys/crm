<?php
namespace App\Http\Requests\Activities;

use App\Models\Reminder;
use App\Http\Requests\Activities\Concerns\ActivityRelatedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    use ActivityRelatedRules;
    public function rules(): array
    {
        return array_merge([
            'title'        => ['required','string','max:191'],
            'remind_at'    => ['required','date'],
            'channel'      => ['nullable', Rule::in(Reminder::CHANNELS)],
        ], $this->relatedRules());
    }
}
