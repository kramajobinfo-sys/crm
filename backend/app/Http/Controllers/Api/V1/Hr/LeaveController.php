<?php
namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\HrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class LeaveController extends Controller
{
    public function __construct(private readonly HrService $hr) {}

    // ---- leave types -----------------------------------------------------

    public function types(): JsonResponse
    {
        return $this->success($this->hr->leaveTypes()->map(fn ($t) => [
            'id' => $t->id, 'name' => $t->name, 'code' => $t->code,
            'days_per_year' => (float) $t->days_per_year, 'is_paid' => (bool) $t->is_paid,
            'color' => $t->color, 'is_active' => (bool) $t->is_active,
        ]));
    }

    public function storeType(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:96',
            'code' => ['required','string','max:32', Rule::unique('leave_types','code')->where('company_id',$companyId)],
            'days_per_year' => 'required|numeric|min:0|max:365',
            'is_paid' => 'nullable|boolean',
            'color' => 'nullable|string|max:16',
        ]);
        $type = $this->hr->createLeaveType($data);
        return $this->success(['id' => $type->id, 'name' => $type->name], 'Leave type created', 201);
    }

    // ---- leave requests --------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status' => 'nullable|string|in:all,pending,approved,rejected,cancelled',
            'employee_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->hr->paginateRequests($f, (int) ($f['per_page'] ?? 25)), LeaveRequestResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'employee_id' => ['required','integer', Rule::exists('employees','id')->where('company_id',$companyId)],
            'leave_type_id' => ['required','integer', Rule::exists('leave_types','id')->where('company_id',$companyId)],
            'start_date' => ['required','date'],
            'end_date' => ['required','date'],
            'reason' => ['nullable','string','max:500'],
        ]);
        try {
            $req = $this->hr->createRequest($data);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new LeaveRequestResource($req), 'Leave requested', 201);
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:approve,reject,cancel',
            'note' => 'nullable|string|max:500',
        ]);
        $req = LeaveRequest::with('employee')->findOrFail($id);
        try {
            $req = match ($data['action']) {
                'approve' => $this->hr->approveRequest($req, $data['note'] ?? null),
                'reject'  => $this->hr->rejectRequest($req, $data['note'] ?? null),
                'cancel'  => $this->hr->cancelRequest($req),
            };
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new LeaveRequestResource($req->load(['employee', 'leaveType', 'approver'])), 'Decision recorded');
    }
}
