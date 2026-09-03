<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates the platform console. Deliberately not a `permission:` check — platform-admin status is
 * a flag on the user (`isPlatformAdmin()`), not a tenant role, so a tenant could never grant it to
 * itself, and it is not a `feature:` check either — a tenant's own plan must never be able to lock
 * the platform out of managing that tenant.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->isPlatformAdmin()) {
            return response()->json(['success' => false, 'message' => 'Platform admin access required'], 403);
        }
        return $next($request);
    }
}
