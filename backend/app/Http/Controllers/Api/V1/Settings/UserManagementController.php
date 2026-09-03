<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'role' => 'nullable|string',
            'is_active' => 'nullable|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::query()
            ->where('company_id', $companyId)
            // `roles` is eager-loaded because AdminUserResource calls getRoleNames() per row —
            // without it the list issues one roles query per user (100 extra at per_page=100).
            ->with(['branch:id,name', 'department:id,name', 'roles:id,name'])
            ->when(!empty($f['q']), fn ($q) => $q->where(fn ($w) =>
                $w->where('name', 'like', '%'.$f['q'].'%')->orWhere('email', 'like', '%'.$f['q'].'%')))
            ->when(isset($f['is_active']), fn ($q) => $q->where('is_active', (bool) $f['is_active']))
            ->when(!empty($f['role']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $f['role'])))
            ->orderBy('name')
            ->paginate((int) ($f['per_page'] ?? 25));

        return $this->paginated($users, AdminUserResource::class);
    }

    public function meta(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        return $this->success([
            'roles' => Role::where('guard_name', 'api')->where('company_id', $companyId)->orderBy('name')->pluck('name'),
            'branches' => Branch::where('company_id', $companyId)->orderBy('name')->get(['id','name']),
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => ['required','email','max:191','unique:users,email'],
            'password' => 'required|string|min:8|max:100',
            'phone' => 'nullable|string|max:32',
            'branch_id' => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'department_id' => ['nullable','integer', Rule::exists('departments','id')->where('company_id',$companyId)],
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => ['string', Rule::exists('roles','name')->where('guard_name','api')->where('company_id',$companyId)],
        ]);

        $user = User::create([
            'company_id' => $companyId,
            'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);
        if (!empty($data['roles'])) $user->syncRoles($this->assignableRoles($request, $data['roles']));

        return $this->success(new AdminUserResource($user->load(['branch:id,name', 'department:id,name'])), 'User created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $user = User::where('company_id', $companyId)->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'email' => ['sometimes','email','max:191', Rule::unique('users','email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|max:100',
            'phone' => 'nullable|string|max:32',
            'branch_id' => ['nullable','integer', Rule::exists('branches','id')->where('company_id',$companyId)],
            'department_id' => ['nullable','integer', Rule::exists('departments','id')->where('company_id',$companyId)],
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => ['string', Rule::exists('roles','name')->where('guard_name','api')->where('company_id',$companyId)],
        ]);

        $patch = collect($data)->only(['name','email','phone','branch_id','department_id','is_active'])->toArray();
        if (!empty($data['password'])) $patch['password'] = Hash::make($data['password']);
        $user->update($patch);
        if (array_key_exists('roles', $data)) $user->syncRoles($this->assignableRoles($request, $data['roles'] ?? []));

        return $this->success(new AdminUserResource($user->load(['branch:id,name', 'department:id,name'])), 'User updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return $this->error('You cannot delete your own account.', 422);
        }
        $user->delete();
        return $this->success(null, 'User deleted');
    }

    /**
     * Only the company Owner (or a platform admin) may grant the Owner role, so a lesser admin
     * cannot escalate a user — or themselves — to full control of the org.
     */
    private function assignableRoles(Request $request, array $roles): array
    {
        $actor = $request->user();
        $canGrantOwner = $actor->isPlatformAdmin() || $actor->hasRole(TenantProvisioner::OWNER_ROLE);
        if (!$canGrantOwner) {
            $roles = array_values(array_filter($roles, fn ($r) => $r !== TenantProvisioner::OWNER_ROLE));
        }
        return $roles;
    }
}
