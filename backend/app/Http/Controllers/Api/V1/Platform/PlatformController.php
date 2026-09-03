<?php
namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SupportAccessGrant;
use App\Services\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform console: cross-tenant company management for platform admins. Every route here is
 * gated by `platform.admin` only (see EnsurePlatformAdmin) — no RBAC permission, no plan feature —
 * since this operates above the tenant boundary those checks are scoped to.
 */
class PlatformController extends Controller
{
    public function companies(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => 'nullable|string|max:191', 'per_page' => 'nullable|integer|min:1|max:100']);
        $companies = Company::withoutGlobalScopes()->with('plan:id,code,name')->withCount('users')
            ->when(!empty($f['q']), fn ($q) => $q->where(fn ($w) =>
                $w->where('name', 'like', '%'.$f['q'].'%')->orWhere('code', 'like', '%'.$f['q'].'%')))
            ->orderBy('name')->paginate((int) ($f['per_page'] ?? 25));
        return $this->paginated($companies, null);
    }

    public function company(int $id): JsonResponse
    {
        $company = Company::withoutGlobalScopes()->with('plan:id,code,name')->withCount('users')->findOrFail($id);
        $grants = SupportAccessGrant::where('company_id', $id)->with('grantedBy:id,name')
            ->orderByDesc('created_at')->limit(20)->get();
        return $this->success([
            'company' => $company,
            'grants' => $grants,
        ]);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $company = Company::withoutGlobalScopes()->findOrFail($id);
        $data = $request->validate(['plan_code' => ['required', 'string', Rule::exists('plans', 'code')]]);
        app(TenantProvisioner::class)->provisionPlan($company, $data['plan_code']);
        return $this->success($company->refresh()->load('plan:id,code,name'), 'Plan updated');
    }

    public function grants(Request $request): JsonResponse
    {
        $grants = SupportAccessGrant::with(['company:id,name,code', 'grantedBy:id,name'])
            ->orderByDesc('created_at')->paginate(25);
        return $this->paginated($grants, null);
    }

    public function grantAccess(Request $request, int $id): JsonResponse
    {
        $company = Company::withoutGlobalScopes()->findOrFail($id);
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'hours' => 'required|integer|min:1|max:8',
        ]);
        $grant = SupportAccessGrant::create([
            'company_id' => $company->id,
            'granted_by' => $request->user()->id,
            'reason' => $data['reason'],
            'expires_at' => now()->addHours($data['hours']),
        ]);
        return $this->success($grant->load('grantedBy:id,name'), 'Access grant recorded', 201);
    }

    public function revokeGrant(Request $request, int $id): JsonResponse
    {
        $grant = SupportAccessGrant::active()->findOrFail($id);
        $grant->update(['revoked_at' => now()]);
        return $this->success(null, 'Access grant revoked');
    }
}
