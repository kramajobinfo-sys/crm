<?php
namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $year = now()->year;

        // Leave types --------------------------------------------------
        $types = [];
        foreach ([
            ['Annual leave','ANNUAL',21,true,'#3B82F6'],
            ['Sick leave','SICK',10,true,'#EF4444'],
            ['Casual leave','CASUAL',5,true,'#8B5CF6'],
            ['Unpaid leave','UNPAID',0,false,'#64748B'],
        ] as [$name,$code,$days,$paid,$color]) {
            $types[$code] = LeaveType::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'days_per_year' => $days, 'is_paid' => $paid, 'color' => $color, 'is_active' => true]
            );
        }

        $depts = Department::withoutGlobalScopes()->where('company_id', $cid)->pluck('id', 'code');

        // Employees (linked to seeded user accounts) -------------------
        // [no, first, last, userEmail, deptCode, title, type, hireDaysAgo, salary]
        $rows = [
            ['EMP-0001','Hana','Ibrahim','hr@krama.local','HR','HR Officer','full_time',900,14000],
            ['EMP-0002','Omar','Saleh','sales.mgr@krama.local','SALES','Sales Manager','full_time',1200,22000],
            ['EMP-0003','Layla','Nasser','sales@krama.local','SALES','Sales Executive','full_time',400,12000],
            ['EMP-0004','Rami','Khaled','accounts@krama.local','ACC','Accountant','full_time',700,16000],
            ['EMP-0005','Sara','Fahad','warehouse@krama.local','WH','Warehouse Supervisor','full_time',300,10000],
            ['EMP-0006','Yusuf','Amin','purchase@krama.local','PURCH','Purchasing Officer','contract',150,11000],
        ];

        $employees = [];
        foreach ($rows as [$no,$first,$last,$email,$deptCode,$title,$type,$hireAgo,$salary]) {
            $uid = User::withoutGlobalScopes()->where('company_id', $cid)->where('email', $email)->value('id');
            $emp = Employee::updateOrCreate(
                ['company_id' => $cid, 'employee_no' => $no],
                [
                    'user_id' => $uid, 'first_name' => $first, 'last_name' => $last,
                    'email' => $email, 'department_id' => $depts[$deptCode] ?? null,
                    'job_title' => $title, 'employment_type' => $type, 'status' => 'active',
                    'hire_date' => now()->subDays($hireAgo)->toDateString(),
                    'salary' => $salary, 'currency' => 'AED',
                ]
            );
            $employees[$no] = $emp;

            // Balances for the current year.
            foreach ($types as $t) {
                LeaveBalance::updateOrCreate(
                    ['employee_id' => $emp->id, 'leave_type_id' => $t->id, 'year' => $year],
                    ['company_id' => $cid, 'entitled' => $t->days_per_year, 'used' => 0]
                );
            }

            // Today's attendance.
            Attendance::updateOrCreate(
                ['employee_id' => $emp->id, 'date' => now()->toDateString()],
                ['company_id' => $cid, 'check_in' => '09:00', 'check_out' => '17:30',
                 'status' => 'present', 'hours_worked' => 8.5]
            );
        }

        // A pending annual-leave request.
        LeaveRequest::updateOrCreate(
            ['company_id' => $cid, 'employee_id' => $employees['EMP-0003']->id, 'start_date' => now()->addDays(10)->toDateString(),
             'leave_type_id' => $types['ANNUAL']->id],
            ['end_date' => now()->addDays(14)->toDateString(), 'days' => 5, 'reason' => 'Family holiday', 'status' => 'pending']
        );

        // An approved sick-leave request (deduct from balance).
        $sickReq = LeaveRequest::updateOrCreate(
            ['company_id' => $cid, 'employee_id' => $employees['EMP-0004']->id, 'start_date' => now()->subDays(6)->toDateString(),
             'leave_type_id' => $types['SICK']->id],
            ['end_date' => now()->subDays(5)->toDateString(), 'days' => 2, 'reason' => 'Flu', 'status' => 'approved',
             'approved_at' => now()->subDays(7)]
        );
        LeaveBalance::where('employee_id', $employees['EMP-0004']->id)
            ->where('leave_type_id', $types['SICK']->id)->where('year', $year)
            ->update(['used' => 2]);

        $this->command?->info('Seeded '.count($types).' leave types, '.count($rows).' employees, balances, attendance, 2 leave requests.');
    }
}
