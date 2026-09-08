<?php
namespace App\Http\Requests\Activities;

use App\Models\Task;
use App\Http\Requests\Activities\Concerns\ActivityRelatedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    use ActivityRelatedRules;
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return array_merge([
            'title'        => ['required','string','max:191'],
            'description'  => ['nullable','string','max:5000'],
            'status'       => ['nullable', Rule::in(Task::STATUSES)],
            'priority'     => ['nullable', Rule::in(Task::PRIORITIES)],
            'assigned_to'  => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'due_at'       => ['nullable','date'],
            'recurrence'       => ['nullable', Rule::in(Task::RECURRENCES)],
            'recurrence_until' => ['nullable','date','after_or_equal:due_at'],
        ], $this->relatedRules());
    }
}
