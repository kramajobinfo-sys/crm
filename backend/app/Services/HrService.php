<?php
namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HrService
{
    // ---- employees -------------------------------------------------------

    public function paginateEmployees(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['department:id,name', 'branch:id,name', 'manager:id,first_name,last_name'])
            ->search($f['q'] ?? null)
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['department_id']), fn ($q) => $q->where('department_id', $f['department_id']))
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function findEmployee(int $id): Employee
    {
        // Self-heal this year's balance rows before rendering them. ensureBalances() used to
        // run only at employee creation, so from Jan 1 the detail page showed no balances at
        // all. firstOrCreate makes it a no-op once the rows exist.
        $employee = Employee::findOrFail($id);
        $this->ensureBalances($employee);

        return Employee::with([
            'department:id,name', 'branch:id,name', 'manager:id,first_name,last_name', 'user:id,name',
            'leaveBalances.leaveType:id,name,color',
            'leaveRequests' => fn ($q) => $q->with('leaveType:id,name')->latest()->limit(10),
        ])->findOrFail($id);
    }

    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $data['employee_no'] ??= $this->nextEmployeeNo();
            $employee = Employee::create($data);
            $this->ensureBalances($employee);
            return $this->findEmployee($employee->id);
        });
    }

    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $employee->update($data);
        return $this->findEmployee($employee->id);
    }

    /**
     * Create a balance row for each active leave type the employee lacks, for $year
     * (default: this year). Called at employee creation AND on employee read, because
     * EmployeeResource renders `leave_balances` off the relation — without the read-side
     * call the enforcement would be correct while the employee page showed no balances at
     * all from Jan 1 until someone happened to file a request.
     */
    public function ensureBalances(Employee $employee, ?int $year = null): void
    {
        $year = $year ?? now()->year;
        foreach (LeaveType::where('is_active', true)->get() as $type) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['company_id' => $employee->company_id, 'entitled' => $type->days_per_year, 'used' => 0]
            );
        }
    }

    public function stats(): array
    {
        $today = now()->toDateString();
        return [
            'headcount'    => Employee::where('status', 'active')->count(),
            'on_leave'     => Employee::where('status', 'on_leave')->count(),
            'present_today'=> Attendance::whereDate('date', $today)->whereIn('status', ['present', 'late', 'half_day'])->count(),
            'pending_leave'=> LeaveRequest::where('status', 'pending')->count(),
            'new_this_month' => Employee::whereMonth('hire_date', now()->month)->whereYear('hire_date', now()->year)->count(),
        ];
    }

    public function nextEmployeeNo(string $prefix = 'EMP'): string
    {
        $companyId = auth()->user()?->company_id;
        $last = Employee::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)->where('employee_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(employee_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('employee_no');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $n);
    }

    // ---- attendance ------------------------------------------------------

    public function attendance(array $f = []): Collection
    {
        $date = $f['date'] ?? now()->toDateString();
        return Attendance::query()
            ->with('employee:id,first_name,last_name,employee_no')
            ->whereDate('date', $date)
            ->when(!empty($f['employee_id']), fn ($q) => $q->where('employee_id', $f['employee_id']))
            ->orderBy('employee_id')->get();
    }

    /** Upsert one attendance row for an employee on a date, computing hours from the clock times. */
    public function logAttendance(array $data): Attendance
    {
        $hours = 0.0;
        if (!empty($data['check_in']) && !empty($data['check_out'])) {
            $in = Carbon::parse($data['check_in']); $out = Carbon::parse($data['check_out']);
            // Operand order matters: Carbon 3's floatDiffInHours is SIGNED (Carbon 2 defaulted
            // to absolute). $out->floatDiffInHours($in) returns $in - $out, i.e. -8.0 for a
            // 09:00-17:00 shift, which max(...,0) then flattened to 0 — every attendance row
            // logged through this endpoint stored hours_worked = 0. Pinned carbon is 3.13.2.
            $hours = max(round($in->floatDiffInHours($out), 2), 0);
        }
        $employee = Employee::findOrFail($data['employee_id']);
        return Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $data['date']],
            [
                'company_id' => $employee->company_id,
                'check_in' => $data['check_in'] ?? null, 'check_out' => $data['check_out'] ?? null,
                'status' => $data['status'] ?? 'present', 'hours_worked' => $hours,
                'notes' => $data['notes'] ?? null,
            ]
        )->load('employee:id,first_name,last_name,employee_no');
    }

    // ---- leave types -----------------------------------------------------

    public function leaveTypes(): Collection
    {
        return LeaveType::orderBy('name')->get();
    }

    public function createLeaveType(array $data): LeaveType
    {
        return LeaveType::create($data);
    }

    // ---- leave requests --------------------------------------------------

    public function paginateRequests(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_no', 'leaveType:id,name,color', 'approver:id,name'])
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['employee_id']), fn ($q) => $q->where('employee_id', $f['employee_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createRequest(array $data): LeaveRequest
    {
        return DB::transaction(function () use ($data) {
            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);
            if ($end->lt($start)) throw new RuntimeException('The end date cannot be before the start date.');
            $days = $start->diffInDays($end) + 1;   // inclusive calendar days

            // Billed to the year the leave STARTS in (a Dec→Jan request draws on the start
            // year's entitlement) — stated explicitly rather than left to now()->year.
            $balance = $this->balanceFor((int) $data['employee_id'], (int) $data['leave_type_id'], $start->year, true);
            if ($balance && $balance->remaining < $days) {
                throw new RuntimeException("Insufficient balance: {$balance->remaining} day(s) left, {$days} requested.");
            }

            return LeaveRequest::create([
                'company_id' => Employee::findOrFail($data['employee_id'])->company_id,
                'employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'days' => $days,
                'reason' => $data['reason'] ?? null, 'status' => 'pending',
            ])->load(['employee:id,first_name,last_name', 'leaveType:id,name']);
        });
    }

    /** Approve: deduct the days from the balance and mark those dates as leave attendance. */
    public function approveRequest(LeaveRequest $request, ?string $note = null): LeaveRequest
    {
        if ($request->status !== 'pending') throw new RuntimeException('Only a pending request can be approved.');

        return DB::transaction(function () use ($request, $note) {
            // Re-read the status under a row lock: the check above happened outside the
            // transaction, so two approvers double-clicking both passed it and both deducted.
            $locked = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw new RuntimeException('This request has already been '.$locked->status.'.');
            }

            $balance = $this->balanceFor($locked->employee_id, $locked->leave_type_id, $locked->start_date->year, true);
            if ($balance) {
                // Re-check at APPROVAL time, not just at request time. Previously approve only
                // incremented: two requests that each passed the check when filed could both be
                // approved, pushing `used` past `entitled` and `remaining` negative with no
                // concurrency involved at all.
                $balance = $balance->newQuery()->whereKey($balance->id)->lockForUpdate()->first();
                if ($balance->remaining < (float) $locked->days) {
                    throw new RuntimeException(
                        "Insufficient balance: {$balance->remaining} day(s) left for {$locked->start_date->year}, "
                        ."{$locked->days} requested."
                    );
                }
                $balance->increment('used', (float) $locked->days);
            }

            // Stamp attendance rows for the leave span.
            for ($d = $request->start_date->copy(); $d->lte($request->end_date); $d->addDay()) {
                Attendance::updateOrCreate(
                    ['employee_id' => $request->employee_id, 'date' => $d->toDateString()],
                    ['company_id' => $request->company_id, 'status' => 'leave', 'hours_worked' => 0]
                );
            }
            $request->forceFill([
                'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decision_note' => $note,
            ])->save();

            return $request->fresh(['employee:id,first_name,last_name', 'leaveType:id,name', 'approver:id,name']);
        });
    }

    public function rejectRequest(LeaveRequest $request, ?string $note = null): LeaveRequest
    {
        if ($request->status !== 'pending') throw new RuntimeException('Only a pending request can be rejected.');
        $request->forceFill([
            'status' => 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decision_note' => $note,
        ])->save();
        return $request->fresh(['employee:id,first_name,last_name', 'leaveType:id,name', 'approver:id,name']);
    }

    /** Cancel an approved/pending request; an approved one returns the days to the balance. */
    public function cancelRequest(LeaveRequest $request): LeaveRequest
    {
        if (in_array($request->status, ['rejected', 'cancelled'], true)) {
            throw new RuntimeException('This request is already '.$request->status.'.');
        }
        return DB::transaction(function () use ($request) {
            // Same lock-and-re-read as approve: two concurrent cancels of one approved request
            // both credited the days back.
            $locked = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['rejected', 'cancelled'], true)) {
                throw new RuntimeException('This request is already '.$locked->status.'.');
            }

            if ($locked->status === 'approved') {
                // The REQUEST's year, and create:false — a request approved under the old
                // no-op check never deducted anything, so there is no row and nothing to give
                // back. Clamped at 0 so a partial-state row can never go negative.
                $balance = $this->balanceFor($locked->employee_id, $locked->leave_type_id, $locked->start_date->year);
                if ($balance) {
                    $balance = $balance->newQuery()->whereKey($balance->id)->lockForUpdate()->first();
                    $balance->forceFill(['used' => max(0, round((float) $balance->used - (float) $locked->days, 1))])->save();
                }
            }
            $request->forceFill(['status' => 'cancelled'])->save();
            return $request->fresh(['employee:id,first_name,last_name', 'leaveType:id,name']);
        });
    }

    /**
     * The balance row for a specific YEAR — never `now()->year`.
     *
     * Two bugs came from keying on the wall clock. (1) ensureBalances() only ever ran at
     * employee creation, so from Jan 1 of the next year this returned null for everyone, and
     * both callers treated null as "no constraint": the check in createRequest was skipped
     * and the deduction in approveRequest was skipped, making leave unlimited and untracked.
     * (2) A request approved in one year and cancelled in the next would have returned its
     * days to the WRONG year's row.
     *
     * So the year is always derived from the request being acted on (its start_date), and
     * $create is false on the cancel path: if no row exists, nothing was ever deducted from
     * it, so there is nothing to give back — creating one there would drive `used` negative
     * (the column is signed, so MySQL would not stop it).
     */
    private function balanceFor(int $employeeId, int $leaveTypeId, int $year, bool $create = false): ?LeaveBalance
    {
        $existing = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)->where('year', $year)->first();
        if ($existing || !$create) return $existing;

        $type = LeaveType::find($leaveTypeId);
        $employee = Employee::find($employeeId);
        if (!$type || !$employee) return null;

        // firstOrCreate, not create: unique (employee_id, leave_type_id, year) means a
        // concurrent request may have just made it.
        return LeaveBalance::firstOrCreate(
            ['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => $year],
            ['company_id' => $employee->company_id, 'entitled' => $type->days_per_year, 'used' => 0]
        );
    }
}
