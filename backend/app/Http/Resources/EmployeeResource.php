<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_no' => $this->employee_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'national_id' => $this->national_id,
            'salary' => (float) $this->salary,
            'currency' => $this->currency,
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'department' => $this->whenLoaded('department', fn () => $this->department
                ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch
                ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager
                ? ['id' => $this->manager->id, 'name' => $this->manager->full_name] : null),
            'leave_balances' => $this->whenLoaded('leaveBalances', fn () => $this->leaveBalances->map(fn ($b) => [
                'id' => $b->id, 'type' => $b->leaveType?->name, 'color' => $b->leaveType?->color,
                'year' => $b->year, 'entitled' => (float) $b->entitled, 'used' => (float) $b->used, 'remaining' => $b->remaining,
            ])),
            'leave_requests' => $this->whenLoaded('leaveRequests', fn () => $this->leaveRequests->map(fn ($r) => [
                'id' => $r->id, 'type' => $r->leaveType?->name, 'status' => $r->status,
                'start_date' => $r->start_date?->toDateString(), 'end_date' => $r->end_date?->toDateString(),
                'days' => (float) $r->days,
            ])),
        ];
    }
}
