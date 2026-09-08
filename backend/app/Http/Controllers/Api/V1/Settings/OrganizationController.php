<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    // ---- company profile -------------------------------------------------

    public function company(Request $request): JsonResponse
    {
        return $this->success($this->companyPayload($request->user()->company));
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'legal_name' => 'nullable|string|max:191',
            'tax_id' => 'nullable|string|max:64',
            'base_currency' => ['nullable','string','size:3', Rule::exists('currencies','code')],
            'primary_color' => 'nullable|string|max:16',
            'default_language' => 'nullable|string|in:en,ar',
            'address_line1' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:96',
            'country' => 'nullable|string|max:96',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:191',
            'website' => 'nullable|url|max:191',
        ]);
        $company->update($data);
        return $this->success($this->companyPayload($company->refresh()), 'Company updated');
    }

    /** Company-wide appearance (theme/accent/sidebar+top-bar colors). When enforced, overrides each
     *  user's personal choice; otherwise it's the default a non-personalized user inherits. */
    public function updateAppearance(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $data = $request->validate([
            'theme' => 'nullable|in:light,dark',
            'accent' => 'nullable|in:blue,indigo,emerald,violet,rose',
            'chrome' => 'nullable|array',
            'chrome.sidebar' => 'nullable|string|max:9',
            'chrome.topbar' => 'nullable|string|max:9',
            'enforced' => 'boolean',
        ]);
        $company->update(['appearance' => [
            'theme' => $data['theme'] ?? null,
            'accent' => $data['accent'] ?? null,
            'chrome' => [
                'sidebar' => $data['chrome']['sidebar'] ?? null,
                'topbar' => $data['chrome']['topbar'] ?? null,
            ],
            'enforced' => (bool) ($data['enforced'] ?? false),
        ]]);
        return $this->success($company->refresh()->appearance, 'Company appearance saved');
    }

    // ---- branches --------------------------------------------------------

    public function branches(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        return $this->success(Branch::where('company_id', $companyId)->with('manager:id,name')
            ->withCount('users')->orderBy('name')->get()->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'code' => $b->code, 'city' => $b->city,
                'country' => $b->country, 'phone' => $b->phone, 'is_active' => (bool) $b->is_active,
                'manager' => $b->manager?->name, 'users_count' => $b->users_count,
            ]));
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $this->branchRules($request, $companyId);
        $branch = Branch::create(array_merge($data, ['company_id' => $companyId]));
        return $this->success(['id' => $branch->id, 'name' => $branch->name], 'Branch created', 201);
    }

    public function updateBranch(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $branch = Branch::where('company_id', $companyId)->findOrFail($id);
        $branch->update($this->branchRules($request, $companyId, $branch->id));
        return $this->success(['id' => $branch->id, 'name' => $branch->name], 'Branch updated');
    }

    public function destroyBranch(Request $request, int $id): JsonResponse
    {
        $branch = Branch::where('company_id', $request->user()->company_id)->withCount('users')->findOrFail($id);
        if ($branch->users_count > 0) return $this->error('This branch still has users assigned.', 422);
        $branch->delete();
        return $this->success(null, 'Branch deleted');
    }

    private function branchRules(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:128',
            'code' => ['required','string','max:32', Rule::unique('branches','code')->where('company_id',$companyId)->ignore($id)],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:96',
            'country' => 'nullable|string|max:96',
            'phone' => 'nullable|string|max:32',
            'manager_id' => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
            'is_active' => 'nullable|boolean',
        ]);
    }

    // ---- departments -----------------------------------------------------

    public function departments(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        return $this->success(Department::where('company_id', $companyId)
            ->with(['branch:id,name', 'head:id,name'])->withCount('users')->orderBy('name')->get()
            ->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->name, 'code' => $d->code,
                'branch' => $d->branch?->name, 'head' => $d->head?->name, 'users_count' => $d->users_count,
            ]));
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $this->departmentRules($request, $companyId);
        $dept = Department::create(array_merge($data, ['company_id' => $companyId]));
        return $this->success(['id' => $dept->id, 'name' => $dept->name], 'Department created', 201);
    }

    public function updateDepartment(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $dept = Department::where('company_id', $companyId)->findOrFail($id);
        $dept->update($this->departmentRules($request, $companyId));
        return $this->success(['id' => $dept->id, 'name' => $dept->name], 'Department updated');
    }

    public function destroyDepartment(Request $request, int $id): JsonResponse
    {
        $dept = Department::where('company_id', $request->user()->company_id)->withCount('users')->findOrFail($id);
        if ($dept->users_count > 0) return $this->error('This department still has users assigned.', 422);
        $dept->delete();
        return $this->success(null, 'Department deleted');
    }

    private function departmentRules(Request $request, int $companyId): array
    {
        return $request->validate([
            'name' => 'required|string|max:128',
            'code' => 'required|string|max:32',
            'branch_id' => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'head_id' => ['nullable','integer', Rule::exists('users','id')->where('company_id',$companyId)],
        ]);
    }

    private function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id, 'name' => $company->name, 'code' => $company->code,
            'legal_name' => $company->legal_name, 'tax_id' => $company->tax_id,
            'base_currency' => $company->base_currency, 'primary_color' => $company->primary_color,
            'default_language' => $company->default_language, 'appearance' => $company->appearance,
            'logo_url' => $company->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo_path) : null,
            'address_line1' => $company->address_line1, 'city' => $company->city, 'country' => $company->country,
            'phone' => $company->phone, 'email' => $company->email, 'website' => $company->website,
        ];
    }
}
