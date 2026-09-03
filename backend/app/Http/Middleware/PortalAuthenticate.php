<?php
namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * Authenticates the customer-portal `portal` guard — a second, parallel JWT boundary to the
 * staff-facing `jwt.auth`/`api` guard. Deliberately its own class rather than reusing
 * JwtAuthenticate: that middleware checks $user->is_active, a column only User has, and always
 * resolves via the default guard rather than an explicit one.
 */
class PortalAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        // Auth::guard('portal')->parseToken()->authenticate() is a trap: parseToken() forwards
        // (via __call) to a package-wide singleton JWT instance that has no authenticate() method
        // — that only exists on the separate JWTAuth facade root, which is bound to one fixed
        // guard's provider and can't be reused across guards. getPayload()+user() are the guard's
        // own methods, correctly scoped to *this* guard's provider (contacts, not users).
        $guard = Auth::guard('portal');
        try {
            $guard->getPayload();
        } catch (TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token has expired', 'token_error' => 'expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token is invalid', 'token_error' => 'invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Authorization token not provided', 'token_error' => 'missing'], 401);
        }

        $contact = $guard->user();
        if (!$contact) return response()->json(['success' => false, 'message' => 'Contact not found'], 401);

        if (!$contact->portal_enabled) {
            return response()->json(['success' => false, 'message' => 'Portal access is disabled'], 403);
        }

        $customer = Customer::withoutGlobalScope('company')
            ->where('id', $contact->customer_id)
            ->where('company_id', $contact->company_id)
            ->first();
        if (!$customer || in_array($customer->status, ['blocked', 'archived'], true)) {
            return response()->json(['success' => false, 'message' => 'Account is unavailable'], 403);
        }

        return $next($request);
    }
}
