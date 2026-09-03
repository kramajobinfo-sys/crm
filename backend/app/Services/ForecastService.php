<?php
namespace App\Services;

use App\Models\Deal;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Forecasts (Zoho gap #10) — quota vs. achieved per user per period. "Achieved/closed" = won deals
 * (won_at in the period); "pipeline" = open deals whose expected_close_date lands in the period,
 * both gross and probability-weighted; "forecast" = closed + weighted pipeline. Amounts are summed
 * in their raw currency (no FX) — the same convention DealService::stats already uses.
 *
 * Aggregates filter company_id EXPLICITLY (never leaning on BelongsToCompany's global scope, which
 * adds no predicate for a platform admin — the class of bug the 2026-08-31 audit fixed).
 */
class ForecastService
{
    /** [start, endExclusive] for a period. endExclusive is the first day of the next period. */
    public function bounds(string $periodType, string $periodStart): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $start = $periodType === 'quarter' ? $start->startOfQuarter() : $start->startOfMonth();
        $end = $periodType === 'quarter' ? (clone $start)->addQuarter() : (clone $start)->addMonth();
        return [$start, $end];
    }

    private function companyId(): int
    {
        $id = auth()->user()?->company_id;
        if (!$id) throw new RuntimeException('No company context.');
        return $id;
    }

    /** The forecast table: one row per relevant user + a totals row. */
    public function forecast(string $periodType, string $periodStart): array
    {
        $cid = $this->companyId();
        [$start, $end] = $this->bounds($periodType, $periodStart);

        // Closed-won per owner (won_at within the period).
        $closed = Deal::query()->where('company_id', $cid)
            ->where('status', 'won')
            ->whereNotNull('won_at')->where('won_at', '>=', $start)->where('won_at', '<', $end)
            ->groupBy('owner_id')
            ->selectRaw('owner_id, COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt')
            ->get()->keyBy('owner_id');

        // Open pipeline per owner (expected_close_date within the period): gross + weighted.
        $pipeline = Deal::query()->where('company_id', $cid)
            ->where('status', 'open')
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '>=', $start->toDateString())
            ->where('expected_close_date', '<', $end->toDateString())
            ->groupBy('owner_id')
            ->selectRaw('owner_id, COALESCE(SUM(amount),0) AS gross, COALESCE(SUM(ROUND(amount*probability/100,2)),0) AS weighted, COUNT(*) AS cnt')
            ->get()->keyBy('owner_id');

        $targets = SalesTarget::where('company_id', $cid)
            ->where('period_type', $periodType)->whereDate('period_start', $start->toDateString())
            ->get()->keyBy('user_id');

        // Users appearing in the forecast: anyone with a target, closed deal, or pipeline this period.
        $userIds = collect([$targets->keys(), $closed->keys(), $pipeline->keys()])
            ->flatten()->filter()->unique()->values();
        $users = User::where('company_id', $cid)->whereIn('id', $userIds)->orderBy('name')->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($userIds as $uid) {
            if (!$users->has($uid)) continue;   // owner no longer in the company
            $target   = (float) ($targets[$uid]->target_amount ?? 0);
            $won      = (float) ($closed[$uid]->total ?? 0);
            $gross    = (float) ($pipeline[$uid]->gross ?? 0);
            $weighted = (float) ($pipeline[$uid]->weighted ?? 0);
            $rows[] = [
                'user_id' => $uid,
                'user' => $users[$uid]->name,
                'target' => round($target, 2),
                'closed' => round($won, 2),
                'won_count' => (int) ($closed[$uid]->cnt ?? 0),
                'pipeline_gross' => round($gross, 2),
                'pipeline_weighted' => round($weighted, 2),
                'pipeline_count' => (int) ($pipeline[$uid]->cnt ?? 0),
                'forecast' => round($won + $weighted, 2),                 // closed + weighted open
                'attainment' => $target > 0 ? round($won / $target * 100, 1) : null,
                'gap' => round($target - $won, 2),
            ];
        }
        // Managers/reps first by target desc, then name.
        usort($rows, fn ($a, $b) => ($b['target'] <=> $a['target']) ?: strcmp($a['user'], $b['user']));

        $sum = fn (string $k) => round(array_sum(array_column($rows, $k)), 2);
        $totTarget = $sum('target');
        $totClosed = $sum('closed');
        $totals = [
            'target' => $totTarget, 'closed' => $totClosed,
            'pipeline_gross' => $sum('pipeline_gross'), 'pipeline_weighted' => $sum('pipeline_weighted'),
            'forecast' => $sum('forecast'),
            'attainment' => $totTarget > 0 ? round($totClosed / $totTarget * 100, 1) : null,
            'gap' => round($totTarget - $totClosed, 2),
        ];

        return [
            'period_type' => $periodType,
            'period_start' => $start->toDateString(),
            'period_end' => (clone $end)->subDay()->toDateString(),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** Targets for a period, keyed for the editor grid (every active user, with 0 default). */
    public function targetsForEditing(string $periodType, string $periodStart): array
    {
        $cid = $this->companyId();
        [$start] = $this->bounds($periodType, $periodStart);
        $existing = SalesTarget::where('company_id', $cid)
            ->where('period_type', $periodType)->whereDate('period_start', $start->toDateString())
            ->pluck('target_amount', 'user_id');

        return User::where('company_id', $cid)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['user_id' => $u->id, 'user' => $u->name, 'target_amount' => (float) ($existing[$u->id] ?? 0)])
            ->all();
    }

    /** Replace the target set for a period. A 0/absent amount clears that user's target. */
    public function setTargets(string $periodType, string $periodStart, array $targets): void
    {
        $cid = $this->companyId();
        [$start] = $this->bounds($periodType, $periodStart);
        $day = $start->toDateString();

        DB::transaction(function () use ($cid, $periodType, $day, $targets) {
            foreach ($targets as $t) {
                $uid = (int) ($t['user_id'] ?? 0);
                if (!$uid) continue;
                $amount = round((float) ($t['target_amount'] ?? 0), 2);
                if ($amount <= 0) {
                    SalesTarget::where('company_id', $cid)->where('user_id', $uid)
                        ->where('period_type', $periodType)->whereDate('period_start', $day)->delete();
                    continue;
                }
                SalesTarget::updateOrCreate(
                    ['company_id' => $cid, 'user_id' => $uid, 'period_type' => $periodType, 'period_start' => $day],
                    ['target_amount' => $amount]
                );
            }
        });
    }
}
