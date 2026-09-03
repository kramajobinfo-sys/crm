<?php
namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\HrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly HrService $hr) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,active,on_leave,terminated',
            'department_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->hr->paginateEmployees($f, (int) ($f['per_page'] ?? 25)), EmployeeResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->hr->stats());
    }

    public function meta(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        return $this->success([
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(['id','name']),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(['id','name']),
            'managers' => Employee::where('company_id', $companyId)->where('status', 'active')
                ->orderBy('first_name')->get()->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name]),
            'employment_types' => Employee::EMPLOYMENT_TYPES,
            'statuses' => Employee::STATUSES,
            'next_employee_no' => $this->hr->nextEmployeeNo(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new EmployeeResource($this->hr->findEmployee($id)));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        return $this->success(new EmployeeResource($this->hr->createEmployee($request->validated())), 'Employee created', 201);
    }

    public function update(StoreEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        return $this->success(new EmployeeResource($this->hr->updateEmployee($employee, $request->validated())), 'Employee updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Employee::findOrFail($id)->delete();
        return $this->success(null, 'Employee deleted');
    }
}
