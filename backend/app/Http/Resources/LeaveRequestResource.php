<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => (float) $this->days,
            'reason' => $this->reason,
            'decision_note' => $this->decision_note,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id, 'name' => $this->employee->full_name,
                'employee_no' => $this->employee->employee_no,
            ] : null),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $this->leaveType
                ? ['id' => $this->leaveType->id, 'name' => $this->leaveType->name, 'color' => $this->leaveType->color] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->name),
        ];
    }
}
