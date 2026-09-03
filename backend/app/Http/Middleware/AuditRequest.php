<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditRequest
{
    private const SKIP_PATHS = ['notifications', 'dashboard'];
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!in_array($request->method(), ['POST','PUT','PATCH','DELETE'])) return $response;
        foreach (self::SKIP_PATHS as $skip) if (str_contains($request->path(), $skip)) return $response;
        try {
            DB::table('audit_logs')->insert([
                'company_id' => auth()->user()?->company_id, 'user_id' => auth()->id(),
                'auditable_type' => 'http.request', 'auditable_id' => 0,
                'event' => strtolower($request->method()), 'old_values' => null,
                'new_values' => json_encode(['path' => $request->path()]),
                'url' => $request->fullUrl(), 'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) { logger()->warning('Audit log failed: '.$e->getMessage()); }
        return $response;
    }
}
