<?php
namespace App\Http\Controllers\Api\V1\System;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $query = AuditLog::query()->where('company_id', auth()->user()->company_id)
            ->with('user:id,name,email')->orderByDesc('created_at');
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('event')) $query->where('event', $request->event);
        if ($request->filled('type')) $query->where('auditable_type', 'like', '%'.$request->type.'%');
        if ($request->filled('from')) $query->where('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->where('created_at', '<=', $request->to);
        return $this->paginated($query->paginate($perPage));
    }
}
