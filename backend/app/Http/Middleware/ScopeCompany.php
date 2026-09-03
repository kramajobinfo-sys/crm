<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
class ScopeCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if ($user && !$user->company_id && !$user->isPlatformAdmin()) {
            return response()->json(['success'=>false,'message'=>'No company context for this user'], 403);
        }
        // Roles are per-company (spatie teams): resolve every role/permission check on this request
        // against the caller's tenant. This runs on every authenticated route, before the
        // permission middleware, so hasPermissionTo() sees the right team.
        if ($user && $user->company_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
        }
        return $next($request);
    }
}
