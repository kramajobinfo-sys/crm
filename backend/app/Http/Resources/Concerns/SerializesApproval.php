<?php
namespace App\Http\Resources\Concerns;

/** Summarise the latest approval on a purchase document into a uniform block. */
trait SerializesApproval
{
    protected function approvalSummary(): ?array
    {
        if (!$this->relationLoaded('approval') || !$this->approval) return null;
        $a = $this->approval;
        $ids = $a->relationLoaded('workflow') && $a->workflow ? ($a->workflow->approver_ids ?? []) : [];

        return [
            'id' => $a->id,
            'status' => $a->status,
            'current_step' => $a->current_step,
            'total_steps' => count($ids),
            'current_approver_id' => $ids[$a->current_step] ?? null,
            'workflow' => $a->relationLoaded('workflow') && $a->workflow
                ? ['id' => $a->workflow->id, 'name' => $a->workflow->name] : null,
            'actions' => $a->relationLoaded('actions') ? $a->actions->map(fn ($x) => [
                'step' => $x->step, 'action' => $x->action, 'comment' => $x->comment,
                'approver' => $x->approver?->name, 'acted_at' => $x->acted_at?->toIso8601String(),
            ]) : [],
        ];
    }
}
