<?php
namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Dashboard aggregator. Uses Schema::hasTable() guards so it runs on day 1
 * before modules 2-15 create their tables, then returns real data as they ship.
 */
class DashboardService
{
    private const CACHE_TTL = 300;

    private function cacheKey(string $name): string
    {
        return "dash:c".(auth()->user()?->company_id ?? 0).":u".auth()->id().":{$name}";
    }

    public function kpis(): array
    {
        return Cache::remember($this->cacheKey('kpis'), self::CACHE_TTL, function () {
            $now = Carbon::now();
            $monthStart = $now->copy()->startOfMonth();
            $prevStart = $now->copy()->subMonth()->startOfMonth();
            $prevEnd = $now->copy()->subMonth()->endOfMonth();

            $revenue = $this->safeSum('invoices', 'grand_total', [['status','in',['issued','partially_paid','paid']], ['issue_date','>=',$monthStart]]);
            $revenuePrev = $this->safeSum('invoices', 'grand_total', [['status','in',['issued','partially_paid','paid']], ['issue_date','>=',$prevStart], ['issue_date','<=',$prevEnd]]);
            $newLeads = $this->safeCount('leads', [['created_at','>=',$monthStart]]);
            $openDeals = $this->safeCount('deals', [['stage_id','not_in_won_lost']]);
            $openTickets = $this->safeCount('tickets', [['status','in',['new','open','pending']]]);
            $breaching = $this->safeCount('tickets', [['due_at','<',$now], ['status','in',['new','open','pending']]]);

            return [
                'revenue' => ['value' => round($revenue,2), 'currency' => auth()->user()->company->base_currency ?? 'USD',
                    'change_pct' => $this->pctChange($revenue, $revenuePrev), 'period' => 'mtd'],
                'new_leads' => ['value' => $newLeads, 'change_pct' => 0, 'period' => 'mtd'],
                'open_deals' => ['value' => $openDeals, 'pipeline_value' => round($this->safeSum('deals','amount',[['stage_id','not_in_won_lost']]),2)],
                'tickets' => ['open' => $openTickets, 'breaching_sla' => $breaching],
            ];
        });
    }

    public function salesChart(int $months = 7): array
    {
        return Cache::remember($this->cacheKey("sales-{$months}"), self::CACHE_TTL,
            fn () => $this->monthlySeries('invoices','grand_total','issue_date',$months,[['status','in',['issued','partially_paid','paid']]]));
    }
    public function purchaseChart(int $months = 7): array
    {
        return Cache::remember($this->cacheKey("purchase-{$months}"), self::CACHE_TTL,
            fn () => $this->monthlySeries('purchase_orders','grand_total','order_date',$months,[['status','in',['confirmed','received','closed']]]));
    }
    public function revenueChart(int $months = 12): array
    {
        return Cache::remember($this->cacheKey("revenue-{$months}"), self::CACHE_TTL,
            fn () => $this->monthlySeries('payments','amount','received_at',$months));
    }

    public function pipeline(): array
    {
        return Cache::remember($this->cacheKey('pipeline'), self::CACHE_TTL, function () {
            if (!Schema::hasTable('deals') || !Schema::hasTable('pipeline_stages')) return $this->placeholderPipeline();
            return DB::table('pipeline_stages as s')
                ->leftJoin('deals as d', fn ($j) => $j->on('d.stage_id','=','s.id')->whereNull('d.deleted_at'))
                ->where('s.company_id', auth()->user()->company_id)->where('s.is_won',0)->where('s.is_lost',0)
                ->groupBy('s.id','s.name','s.color','s.order_index')->orderBy('s.order_index')
                ->select(['s.id','s.name','s.color', DB::raw('COUNT(d.id) as deal_count'), DB::raw('COALESCE(SUM(d.amount),0) as total_value')])
                ->get()->map(fn ($r) => ['stage_id'=>$r->id,'name'=>$r->name,'color'=>$r->color,'count'=>(int)$r->deal_count,'total_value'=>(float)$r->total_value])->all();
        });
    }

    public function topPerformers(int $limit = 5): array
    {
        // The limit is part of the key, as it is for salesChart/purchaseChart/revenueChart —
        // without it a ?limit=20 response was served back for a later ?limit=3 call.
        return Cache::remember($this->cacheKey("top:{$limit}"), self::CACHE_TTL, function () use ($limit) {
            if (!Schema::hasTable('invoices')) return [];
            return DB::table('invoices')->join('users','users.id','=','invoices.created_by')
                ->where('invoices.company_id', auth()->user()->company_id)
                ->whereIn('invoices.status',['issued','partially_paid','paid'])
                ->where('invoices.issue_date','>=',now()->startOfMonth())
                ->groupBy('users.id','users.name','users.avatar_path')->orderByDesc(DB::raw('SUM(invoices.grand_total)'))->limit($limit)
                ->select(['users.id','users.name','users.avatar_path', DB::raw('SUM(invoices.grand_total) as total'), DB::raw('COUNT(invoices.id) as deals_closed')])
                ->get()->map(fn ($r) => ['user_id'=>$r->id,'name'=>$r->name,'avatar_url'=>$r->avatar_path?Storage::disk('public')->url($r->avatar_path):null,'total'=>(float)$r->total,'deals_closed'=>(int)$r->deals_closed])->all();
        });
    }

    public function tasksSummary(): array
    {
        if (!Schema::hasTable('tasks')) return ['total'=>0,'open'=>0,'overdue'=>0,'today'=>0,'items'=>[]];
        $base = DB::table('tasks')->where('company_id', auth()->user()->company_id)->where('assigned_to', auth()->id());
        $today = now()->startOfDay(); $tomorrow = $today->copy()->addDay();
        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereIn('status',['open','in_progress'])->count(),
            'overdue' => (clone $base)->where('due_at','<',now())->whereIn('status',['open','in_progress'])->count(),
            'today' => (clone $base)->whereBetween('due_at',[$today,$tomorrow])->count(),
            'items' => (clone $base)->whereIn('status',['open','in_progress'])->orderBy('due_at')->limit(6)
                ->select('id','title','priority','due_at','status')->get()->all(),
        ];
    }

    public function recentActivity(int $limit = 10): array
    {
        if (!Schema::hasTable('audit_logs')) return [];
        return DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')
            ->where('audit_logs.company_id', auth()->user()->company_id)->orderByDesc('audit_logs.created_at')->limit($limit)
            ->select(['audit_logs.id','audit_logs.event','audit_logs.auditable_type','audit_logs.auditable_id','audit_logs.created_at','users.name as user_name'])
            ->get()->map(fn ($r) => ['id'=>$r->id,'event'=>$r->event,'subject'=>class_basename($r->auditable_type).' #'.$r->auditable_id,'user'=>$r->user_name,'when'=>Carbon::parse($r->created_at)->diffForHumans()])->all();
    }

    public function aiInsights(): array
    {
        // Surface the AI Assistant's live insights (Module 15) when available.
        if (Schema::hasTable('ai_insights')) {
            $rows = DB::table('ai_insights')
                ->where('company_id', auth()->user()->company_id)->where('is_dismissed', 0)
                ->orderByRaw("FIELD(level,'critical','warning','info')")->latest('generated_at')->limit(5)
                ->get(['level','title','body']);
            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($r) => ['level'=>$r->level,'icon'=>'sparkles','title'=>$r->title,'body'=>$r->body])->all();
            }
        }
        return [['level'=>'info','icon'=>'sparkles','title'=>'Welcome to Krama CRM',
            'body'=>'Open the AI Assistant and refresh insights to surface at-risk deals, overdue invoices and SLA breaches here.']];
    }

    public function summary(): array
    {
        return ['kpis'=>$this->kpis(),'sales_chart'=>$this->salesChart(),'pipeline'=>$this->pipeline(),
            'top_performers'=>$this->topPerformers(),'tasks'=>$this->tasksSummary(),'recent'=>$this->recentActivity(),'ai_insights'=>$this->aiInsights()];
    }

    private function safeCount(string $t, array $c = []): int
    {
        if (!Schema::hasTable($t)) return 0;
        $q = DB::table($t)->where('company_id', auth()->user()->company_id);
        $this->excludeTrashed($q, $t);
        $this->applyConditions($q, $c, $t);
        return $q->count();
    }
    private function safeSum(string $t, string $col, array $c = []): float
    {
        if (!Schema::hasTable($t) || !Schema::hasColumn($t, $col)) return 0.0;
        $q = DB::table($t)->where('company_id', auth()->user()->company_id);
        $this->excludeTrashed($q, $t);
        $this->applyConditions($q, $c, $t);
        return (float) $q->sum($col);
    }
    /** Raw DB::table() bypasses the SoftDeletes scope, so exclude trashed rows by hand. */
    private function excludeTrashed($q, string $t): void
    {
        if (Schema::hasColumn($t, 'deleted_at')) $q->whereNull('deleted_at');
    }
    private function applyConditions($q, array $conditions, string $t): void
    {
        foreach ($conditions as $cond) {
            // Sentinel conditions like ['stage_id','not_in_won_lost'] carry no value, so
            // pad rather than list-destructure (which would notice the missing 3rd element).
            [$col, $op, $val] = array_pad($cond, 3, null);
            if ($col === 'stage_id' && $op === 'not_in_won_lost') {
                if (Schema::hasTable('pipeline_stages')) {
                    $ex = DB::table('pipeline_stages')
                        ->where('company_id', auth()->user()->company_id)
                        ->where(fn ($x) => $x->where('is_won',1)->orWhere('is_lost',1))->pluck('id');
                    $q->whereNotIn('stage_id', $ex);
                }
                continue;
            }
            if (strtolower($op) === 'in') $q->whereIn($col, $val); else $q->where($col, $op, $val);
        }
    }
    private function monthlySeries(string $t, string $col, string $dateCol, int $months, array $c = []): array
    {
        $points = []; $now = Carbon::now()->startOfMonth();
        if (!Schema::hasTable($t)) {
            for ($i = $months-1; $i >= 0; $i--) { $m = $now->copy()->subMonths($i); $points[] = ['label'=>$m->format('M'),'value'=>0]; }
            return $points;
        }
        for ($i = $months-1; $i >= 0; $i--) {
            $ms = $now->copy()->subMonths($i); $me = $ms->copy()->endOfMonth();
            $q = DB::table($t)->where('company_id', auth()->user()->company_id)->whereBetween($dateCol, [$ms, $me]);
            $this->excludeTrashed($q, $t);
            $this->applyConditions($q, $c, $t);
            $points[] = ['label' => $ms->format('M'), 'value' => (float) $q->sum($col)];
        }
        return $points;
    }
    private function pctChange(float $cur, float $prev): ?float { return $prev == 0.0 ? null : round((($cur-$prev)/$prev)*100, 1); }
    private function placeholderPipeline(): array
    {
        return [
            ['stage_id'=>0,'name'=>'Qualification','color'=>'#7F77DD','count'=>0,'total_value'=>0],
            ['stage_id'=>0,'name'=>'Proposal','color'=>'#378ADD','count'=>0,'total_value'=>0],
            ['stage_id'=>0,'name'=>'Negotiation','color'=>'#1D9E75','count'=>0,'total_value'=>0],
            ['stage_id'=>0,'name'=>'Closing','color'=>'#EF9F27','count'=>0,'total_value'=>0],
        ];
    }
}
