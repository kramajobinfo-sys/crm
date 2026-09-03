<?php
namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    // The tenant's owner role is protected from edit/delete within the tenant.
    private const PROTECTED = ['Owner'];

    public function index(): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        // Roles are per-company: only ever this tenant's own catalogue.
        $roles = Role::where('guard_name', 'api')->where('company_id', $companyId)
            ->withCount('permissions')->orderBy('name')->get();
        // Qualify company_id — model_has_roles also carries it now, so the bare column is ambiguous.
        $userCounts = User::where('users.company_id', $companyId)
            ->join('model_has_roles', function ($j) {
                $j->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class);
            })
            ->selectRaw('model_has_roles.role_id as role_id, COUNT(*) as c')
            ->groupBy('model_has_roles.role_id')->pluck('c', 'role_id');

        return $this->success($roles->map(fn (Role $r) => [
            'id' => $r->id, 'name' => $r->name,
            'permissions_count' => $r->permissions_count,
            'users_count' => (int) ($userCounts[$r->id] ?? 0),
            'is_protected' => in_array($r->name, self::PROTECTED, true),
        ]));
    }

    public function permissions(): JsonResponse
    {
        $grouped = Permission::where('guard_name', 'api')->orderBy('module')->orderBy('name')->get()
            ->groupBy('module')->map(fn ($perms) => $perms->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]))
            ->toArray();
        return $this->success($grouped);
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::where('guard_name', 'api')->where('company_id', auth()->user()->company_id)
            ->with('permissions:id,name')->findOrFail($id);
        return $this->success([
            'id' => $role->id, 'name' => $role->name,
            'is_protected' => in_array($role->name, self::PROTECTED, true),
            'permissions' => $role->permissions->pluck('name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => ['required','string','max:64',
                Rule::unique('roles','name')->where('guard_name','api')->where('company_id',$companyId)],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::exists('permissions','name')->where('guard_name','api')],
        ]);
        // company_id is stamped from the team context, but set it explicitly so the row can never
        // land unscoped.
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'api', 'company_id' => $companyId]);
        $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        return $this->success(['id' => $role->id, 'name' => $role->name], 'Role created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $role = Role::where('guard_name', 'api')->where('company_id', $companyId)->findOrFail($id);
        if (in_array($role->name, self::PROTECTED, true)) {
            return $this->error('The '.$role->name.' role cannot be modified.', 422);
        }
        $data = $request->validate([
            'name' => ['sometimes','string','max:64',
                Rule::unique('roles','name')->where('guard_name','api')->where('company_id',$companyId)->ignore($role->id)],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::exists('permissions','name')->where('guard_name','api')],
        ]);
        if (!empty($data['name'])) $role->update(['name' => $data['name']]);
        if (array_key_exists('permissions', $data)) $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        return $this->success(['id' => $role->id, 'name' => $role->name], 'Role updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::where('guard_name', 'api')->where('company_id', auth()->user()->company_id)->findOrFail($id);
        if (in_array($role->name, self::PROTECTED, true)) {
            return $this->error('The '.$role->name.' role cannot be deleted.', 422);
        }
        if ($role->users()->count() > 0) {
            return $this->error('This role is still assigned to users.', 422);
        }
        $role->delete();
        return $this->success(null, 'Role deleted');
    }

    /**
     * Start a new role from an existing one's permission set — including a protected role's, since
     * cloning only ever creates a new, unprotected role and never touches the source.
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $source = Role::where('guard_name', 'api')->where('company_id', $companyId)
            ->with('permissions:id,name')->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64',
                Rule::unique('roles', 'name')->where('guard_name', 'api')->where('company_id', $companyId)],
        ]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'api', 'company_id' => $companyId]);
        $role->syncPermissions($source->permissions->pluck('name'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        return $this->success(['id' => $role->id, 'name' => $role->name], 'Role cloned', 201);
    }
}
