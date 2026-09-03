<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtRefresh
{
    public function handle(Request $request, Closure $next)
    {
        try { $newToken = JWTAuth::parseToken()->refresh(); }
        catch (JWTException $e) { return response()->json(['success'=>false,'message'=>'Could not refresh token'], 401); }
        $response = $next($request);
        $response->headers->set('Authorization', 'Bearer '.$newToken);
        return $response;
    }
}
