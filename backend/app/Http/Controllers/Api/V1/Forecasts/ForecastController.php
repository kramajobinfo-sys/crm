<?php
namespace App\Http\Controllers\Api\V1\Forecasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forecasts\SetTargetsRequest;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ForecastController extends Controller
{
    public function __construct(private readonly ForecastService $forecasts) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'period_type'  => 'nullable|in:month,quarter',
            'period_start' => 'nullable|date',
        ]);
        $type = $f['period_type'] ?? 'month';
        $start = $f['period_start'] ?? now()->toDateString();
        return $this->success($this->forecasts->forecast($type, $start));
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'users' => User::where('company_id', auth()->user()->company_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'period_types' => SalesTarget::PERIOD_TYPES,
            'current' => [
                'month'   => now()->startOfMonth()->toDateString(),
                'quarter' => now()->startOfQuarter()->toDateString(),
            ],
        ]);
    }

    public function targets(Request $request): JsonResponse
    {
        $f = $request->validate([
            'period_type'  => 'nullable|in:month,quarter',
            'period_start' => 'nullable|date',
        ]);
        $type = $f['period_type'] ?? 'month';
        $start = $f['period_start'] ?? now()->toDateString();
        return $this->success([
            'period_type' => $type,
            'period_start' => $this->forecasts->bounds($type, $start)[0]->toDateString(),
            'targets' => $this->forecasts->targetsForEditing($type, $start),
        ]);
    }

    public function setTargets(SetTargetsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->forecasts->setTargets($data['period_type'], $data['period_start'], $data['targets']);
        return $this->success($this->forecasts->targetsForEditing($data['period_type'], $data['period_start']), 'Targets saved');
    }
}
