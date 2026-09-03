<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Enforces the second factor for users who have it enabled.
 *
 * This API is stateless (JWT, no session middleware), so the verification state lives in
 * the token itself as a `twofa` claim. The previous implementation read
 * `session('2fa_verified')`, which nothing on this stack ever sets — so it would have
 * rejected every 2FA user on every request had it ever been attached to a route. It never
 * was: the `2fa` alias was registered in bootstrap/app.php and applied to zero routes,
 * which made the whole feature advisory. Login returned a fully privileged token alongside
 * `requires_2fa: true` and nothing checked it again.
 *
 * Flow: login mints `twofa=pending` for a 2FA-enabled user; that token reaches only the
 * exempt auth routes (see routes/api.php) and POST /auth/2fa/verify exchanges it for a
 * token carrying `twofa=ok`.
 *
 * Keeping the state in the claim rather than in a server-side marker keyed on the token is
 * deliberate: a pending token can never become verified, so one captured before
 * verification stays useless. A cache marker would retroactively validate it.
 */
class RequireTwoFactor
{
    public const CLAIM    = 'twofa';
    public const PENDING  = 'pending';
    public const VERIFIED = 'ok';

    public function handle(Request $request, Closure $next)
    {
        // Machine-to-machine auth: the API key *is* the credential, and there is no token to
        // carry a claim — JwtAuthenticate resolves the key and calls setUser() directly. A
        // second factor is an interactive check, so it cannot apply to a key.
        if ($request->hasHeader('X-Api-Key')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || !$user->two_factor_enabled) {
            return $next($request);
        }

        if ($this->tokenIsVerified($user)) {
            return $next($request);
        }

        return response()->json([
            'success'      => false,
            'message'      => 'Two-factor verification required',
            'requires_2fa' => true,
        ], 403);
    }

    /** Fail closed — any trouble reading the claim (e.g. no token at all) counts as unverified. */
    private function tokenIsVerified($user): bool
    {
        try {
            $payload = Auth::guard('api')->payload();
            if ($payload->get(self::CLAIM) !== self::VERIFIED) {
                return false;
            }

            // A token minted BEFORE the factor was switched on carries twofa=ok simply
            // because 2FA was off at login. Honouring it would let every pre-existing
            // session keep full access without ever presenting a code, so the claim is only
            // trusted if the token was issued at or after the confirmation.
            $confirmedAt = $user->two_factor_confirmed_at;
            if ($confirmedAt && (int) $payload->get('iat') < $confirmedAt->getTimestamp()) {
                return false;
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
