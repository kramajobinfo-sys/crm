<?php
namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Dashboard;
use App\Models\ReportExport;
use App\Models\SavedReport;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function datasets(): JsonResponse
    {
        return $this->success($this->reports->datasetsMeta());
    }

    /** Ad-hoc run without saving. */
    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dataset' => 'required|string|max:48',
            'dimension' => 'nullable|string|max:48',
            'measures' => 'nullable|array',
            'measures.*' => 'string|max:48',
            'filters' => 'nullable|array',
        ]);
        try {
            $result = $this->reports->run($data['dataset'], $data['dimension'] ?? null, $data['measures'] ?? [], $data['filters'] ?? []);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success($result);
    }

    public function index(): JsonResponse
    {
        return $this->success($this->reports->reports()->map(fn (SavedReport $r) => [
            'id' => $r->id, 'name' => $r->name, 'description' => $r->description,
            'dataset' => $r->dataset, 'dimension' => $r->dimension, 'measures' => $r->measures,
            'filters' => $r->filters, 'chart_type' => $r->chart_type, 'creator' => $r->creator?->name,
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $report = SavedReport::findOrFail($id);
        try {
            $result = $this->reports->runSaved($report);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success([
            'report' => [
                'id' => $report->id, 'name' => $report->name, 'description' => $report->description,
                'dataset' => $report->dataset, 'dimension' => $report->dimension, 'measures' => $report->measures,
                'filters' => $report->filters, 'chart_type' => $report->chart_type,
            ],
            'result' => $result,
        ]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = $this->reports->createReport($request->validated());
        return $this->success(['id' => $report->id, 'name' => $report->name], 'Report saved', 201);
    }

    public function update(StoreReportRequest $request, int $id): JsonResponse
    {
        $report = SavedReport::findOrFail($id);
        $this->reports->updateReport($report, $request->validated());
        return $this->success(['id' => $report->id, 'name' => $report->name], 'Report updated');
    }

    public function destroy(int $id): JsonResponse
    {
        SavedReport::findOrFail($id)->delete();
        return $this->success(null, 'Report deleted');
    }

    public function export(int $id): JsonResponse
    {
        $report = SavedReport::findOrFail($id);
        // Same guard run() and show() already have: a saved report can hold a dataset/dimension
        // that no longer resolves (StoreReportRequest validates them only as strings), and the
        // service throws for it. Uncaught, exporting such a report 500s while GET on the same
        // row correctly returns 422.
        try {
            $export = $this->reports->export($report);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'id' => $export->id, 'status' => $export->status, 'row_count' => $export->row_count,
            'download_url' => $this->exportDownloadUrl($export->id), 'format' => $export->format,
        ], 'Export generated', 201);
    }

    public function exports(int $id): JsonResponse
    {
        $report = SavedReport::findOrFail($id);
        return $this->success($this->reports->exports($report)->map(fn ($e) => [
            'id' => $e->id, 'format' => $e->format, 'status' => $e->status, 'row_count' => $e->row_count,
            'download_url' => $this->exportDownloadUrl($e->id),
            'generated_at' => $e->generated_at?->toIso8601String(),
        ]));
    }

    /**
     * Authenticated download of a generated export.
     *
     * Replaces the old public-disk URL: exports were written to storage/app/public under
     * report-{reportId}-{exportId}.csv with both ids global auto-increments, so they were
     * readable by anyone who could guess a path — no token, no permission, no company scope.
     * Resolving through ReportExport (BelongsToCompany) is the authorisation: another
     * tenant's id simply is not found. The path streamed is always the stored one, never
     * anything from the request.
     */
    public function downloadExport(int $exportId): StreamedResponse
    {
        $export = ReportExport::findOrFail($exportId);
        abort_if($export->status !== 'done' || !$export->path, 404);

        $disk = Storage::disk($export->disk ?: 'local');
        abort_unless($disk->exists($export->path), 404);

        return $disk->download($export->path, basename($export->path));
    }

    private function exportDownloadUrl(int $exportId): string
    {
        return url("/api/v1/reports/exports/{$exportId}/download");
    }

    // ---- custom dashboards ----------------------------------------------

    public function dashboards(): JsonResponse
    {
        return $this->success(Dashboard::with('creator:id,name')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'layout' => $d->layout,
                'is_default' => (bool) $d->is_default, 'creator' => $d->creator?->name]));
    }

    public function storeDashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'layout' => 'nullable|array',
            'layout.*.report_id' => ['required_with:layout', 'integer', Rule::exists('saved_reports', 'id')->where('company_id', auth()->user()->company_id)->whereNull('deleted_at')],
            'layout.*.size' => ['required_with:layout', Rule::in(['half', 'full'])],
            'is_default' => 'nullable|boolean',
        ]);
        $data['created_by'] = auth()->id();
        // Scoped explicitly: BelongsToCompany's global scope is disabled for platform admins,
        // so an unscoped update here clears every tenant's default dashboard.
        if (!empty($data['is_default'])) {
            Dashboard::where('company_id', auth()->user()->company_id)
                ->where('is_default', true)->update(['is_default' => false]);
        }
        $dash = Dashboard::create($data);
        return $this->success(['id' => $dash->id, 'name' => $dash->name], 'Dashboard created', 201);
    }

    public function updateDashboard(Request $request, int $id): JsonResponse
    {
        $dash = Dashboard::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'layout' => 'nullable|array',
            'layout.*.report_id' => ['required_with:layout', 'integer', Rule::exists('saved_reports', 'id')->where('company_id', auth()->user()->company_id)->whereNull('deleted_at')],
            'layout.*.size' => ['required_with:layout', Rule::in(['half', 'full'])],
            'is_default' => 'nullable|boolean',
        ]);
        if (!empty($data['is_default'])) {
            Dashboard::where('company_id', auth()->user()->company_id)
                ->where('id', '!=', $dash->id)->where('is_default', true)->update(['is_default' => false]);
        }
        $dash->update($data);
        return $this->success(['id' => $dash->id, 'name' => $dash->name], 'Dashboard updated');
    }

    public function destroyDashboard(int $id): JsonResponse
    {
        Dashboard::findOrFail($id)->delete();
        return $this->success(null, 'Dashboard deleted');
    }
}
