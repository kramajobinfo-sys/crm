<?php
namespace App\Http\Requests\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowAction;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function rules(): array
    {
        $entity = $this->input('entity');
        $events = Workflow::EVENTS[$entity] ?? array_merge(...array_values(Workflow::EVENTS));

        return [
            'name' => ['required','string','max:191'],
            'description' => ['nullable','string','max:500'],
            'entity' => ['required', Rule::in(Workflow::ENTITIES)],
            'trigger_type' => ['required', Rule::in(Workflow::TRIGGER_TYPES)],
            'trigger_event' => ['required_if:trigger_type,event', 'nullable', 'string', Rule::in($events)],
            'conditions' => ['nullable','array'],
            'conditions.*.field' => ['required_with:conditions','string','max:64'],
            'conditions.*.op' => ['nullable','string','in:eq,neq,gt,gte,lt,lte,contains'],
            'conditions.*.value' => ['nullable'],
            'schedule_cron' => ['required_if:trigger_type,schedule', 'nullable', 'string', 'max:64', function ($attribute, $value, $fail) {
                if ($value && !$this->isValidCron($value)) $fail('The schedule cron expression is not valid.');
            }],
            'is_active' => ['nullable','boolean'],
            'actions' => ['nullable','array'],
            'actions.*.type' => ['required_with:actions', Rule::in(WorkflowAction::TYPES)],
            'actions.*.config' => ['nullable','array'],
        ];
    }

    private function isValidCron(string $expression): bool
    {
        try {
            new CronExpression($expression);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
