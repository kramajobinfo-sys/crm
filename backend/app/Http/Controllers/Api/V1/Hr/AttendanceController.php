<?php
namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\HrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private readonly HrService $hr) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'date' => 'nullable|date',
            'employee_id' => 'nullable|integer',
        ]);
        return $this->success($this->hr->attendance($f)->map(fn ($a) => [
            'id' => $a->id, 'employee_id' => $a->employee_id,
            'employee' => $a->employee?->full_name, 'employee_no' => $a->employee?->employee_no,
            'date' => $a->date?->toDateString(), 'check_in' => $a->check_in, 'check_out' => $a->check_out,
            'status' => $a->status, 'hours_worked' => (float) $a->hours_worked, 'notes' => $a->notes,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'employee_id' => ['required','integer', Rule::exists('employees','id')->where('company_id',$companyId)],
            'date' => ['required','date'],
            'check_in' => ['nullable','date_format:H:i'],
            'check_out' => ['nullable','date_format:H:i'],
            'status' => ['nullable', Rule::in(Attendance::STATUSES)],
            'notes' => ['nullable','string','max:255'],
        ]);
        $a = $this->hr->logAttendance($data);
        return $this->success([
            'id' => $a->id, 'employee' => $a->employee?->full_name, 'date' => $a->date?->toDateString(),
            'status' => $a->status, 'hours_worked' => (float) $a->hours_worked,
        ], 'Attendance saved', 201);
    }
}
