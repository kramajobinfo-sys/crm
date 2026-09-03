<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}
    public function summary(): JsonResponse { return $this->success($this->dashboard->summary()); }
    public function kpis(): JsonResponse { return $this->success($this->dashboard->kpis()); }
    public function salesChart(): JsonResponse { return $this->success($this->dashboard->salesChart($this->months(7))); }
    public function purchaseChart(): JsonResponse { return $this->success($this->dashboard->purchaseChart($this->months(7))); }
    public function revenueChart(): JsonResponse { return $this->success($this->dashboard->revenueChart($this->months(12))); }
    public function pipeline(): JsonResponse { return $this->success($this->dashboard->pipeline()); }
    public function topPerformers(): JsonResponse { return $this->success($this->dashboard->topPerformers($this->limit(5))); }
    public function tasksSummary(): JsonResponse { return $this->success($this->dashboard->tasksSummary()); }
    public function recentActivity(): JsonResponse { return $this->success($this->dashboard->recentActivity($this->limit(10))); }
    public function aiInsights(): JsonResponse { return $this->success($this->dashboard->aiInsights()); }
    private function months(int $default): int { return max(3, min((int) request()->query('months', $default), 24)); }

    /**
     * Clamped like months(). Unclamped, `?limit=-1` removed the LIMIT entirely — the query
     * builder ignores a negative — so recent-activity hydrated the whole audit_logs table
     * and ran diffForHumans() per row; `?limit=999999` did the same without the trick.
     */
    private function limit(int $default): int { return max(1, min((int) request()->query('limit', $default), 100)); }
}
