<?php
namespace App\Http\Middleware;
use App\Models\PlanFeature;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates a route group by the caller's tenant plan (edition), independent of RBAC. A user can
 * hold the `leads.view` permission and still be blocked here if their company's plan doesn't
 * include the `leads` module — permission answers "is this role allowed", this answers "did this
 * tenant's plan include this module". Platform admins bypass it, same as the company scope.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $user = $request->user();
        if (!$user || $user->isPlatformAdmin() || !$user->company_id) {
            return $next($request);
        }
        $planId = $user->company?->plan_id;
        $allowed = $planId && PlanFeature::where('plan_id', $planId)->where('module', $module)->exists();
        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => "The \"{$module}\" module isn't included in your plan.",
            ], 403);
        }
        return $next($request);
    }
}
