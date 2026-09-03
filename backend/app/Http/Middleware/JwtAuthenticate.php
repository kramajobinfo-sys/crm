<?php
namespace App\Http\Middleware;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        // A third-party integration authenticates with X-Api-Key instead of a human JWT session.
        // The key resolves to a real user, so RBAC / company scoping / audit stamping downstream
        // all work unchanged — see App\Models\ApiKey.
        if ($request->hasHeader('X-Api-Key')) {
            return $this->handleApiKey($request, $next);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['success'=>false,'message'=>'User not found'], 401);
            if (!$user->is_active) return response()->json(['success'=>false,'message'=>'Account is disabled'], 403);
        } catch (TokenExpiredException $e) {
            return response()->json(['success'=>false,'message'=>'Token has expired','token_error'=>'expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['success'=>false,'message'=>'Token is invalid','token_error'=>'invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['success'=>false,'message'=>'Authorization token not provided','token_error'=>'missing'], 401);
        }
        return $next($request);
    }

    private function handleApiKey(Request $request, Closure $next)
    {
        $key = (string) $request->header('X-Api-Key');
        $apiKey = ApiKey::where('key_hash', hash('sha256', $key))->where('is_active', true)->with('user')->first();

        if (!$apiKey || !$apiKey->user || !$apiKey->user->is_active) {
            return response()->json(['success'=>false,'message'=>'Invalid or inactive API key'], 401);
        }
        if ($apiKey->isExpired()) {
            return response()->json(['success'=>false,'message'=>'API key has expired'], 401);
        }

        Auth::guard('api')->setUser($apiKey->user);
        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
