<?php
namespace App\Http\Controllers\Api\V1\System;
use App\Http\Controllers\Controller;
use App\Models\PlanFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);
        $term = trim($request->input('q'));
        $companyId = auth()->user()->company_id;
        $like = '%'.str_replace('%','\%',$term).'%';
        $results = [];
        $tables = ['leads'=>['name','email','company_name'],'customers'=>['name','email','customer_no','tax_id'],
            'contacts'=>['name','email','phone','mobile'],'deals'=>['title','deal_no'],'tickets'=>['subject','ticket_no']];

        // This route carries no `permission:`/`feature:` middleware of its own, because it spans
        // five modules. Each table is therefore gated here with the SAME permission and plan
        // module its own route group uses — otherwise a Warehouse-role user (no customers.view)
        // could read customer names and emails through search, and a Starter-plan tenant could
        // read tickets its plan excludes.
        $gates = [
            'leads'     => ['leads.view',     'leads'],
            'customers' => ['customers.view', 'customers'],
            'contacts'  => ['contacts.view',  'contacts'],
            'deals'     => ['deals.view',     'deals'],
            'tickets'   => ['tickets.view',   'tickets'],
        ];

        foreach ($tables as $table => $cols) {
            if (!Schema::hasTable($table)) continue;
            [$permission, $module] = $gates[$table];
            if (!$this->maySearch($permission, $module)) continue;
            // Without this guard, a table whose searchable columns are all absent yields an
            // empty WHERE closure — which matches every row instead of none.
            $usable = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if (!$usable) continue;
            $q = DB::table($table)->where('company_id', $companyId);
            if (Schema::hasColumn($table, 'deleted_at')) $q->whereNull('deleted_at');
            $q->where(function ($qq) use ($usable, $like) {
                foreach ($usable as $c) $qq->orWhere($c, 'like', $like);
            });
            $rows = $q->limit(5)->get();
            if ($rows->isEmpty()) continue;
            $results[$table] = $rows->map(function ($r) use ($table) {
                $title = $r->name ?? $r->title ?? $r->subject ?? '#'.$r->id;
                return ['id'=>$r->id,'type'=>$table,'title'=>$title ?: '#'.$r->id,
                    'subtitle'=>$r->email ?? $r->customer_no ?? $r->ticket_no ?? $r->deal_no ?? null];
            });
        }
        return $this->success($results);
    }

    /**
     * Mirrors the `permission:` + `feature:` middleware pair each module's own routes use.
     * Platform admins bypass both, exactly as EnsureFeatureEnabled and the permission gate do.
     */
    private function maySearch(string $permission, string $module): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isPlatformAdmin()) return true;
        if (!$user->can($permission)) return false;

        $planId = $user->company?->plan_id;
        return (bool) $planId && PlanFeature::where('plan_id', $planId)->where('module', $module)->exists();
    }
}
