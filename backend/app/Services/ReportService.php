<?php
namespace App\Services;

use App\Models\ReportExport;
use App\Models\SavedReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Report engine over a whitelisted dataset registry. The user only ever supplies *keys*
 * (dataset / dimension / measure / filter) that are validated against the registry — every
 * SQL fragment comes from the registry and every value is bound, so no raw SQL is accepted.
 */
class ReportService
{
    /** The dataset registry. `select`/`group`/`sql` are trusted; keys and values are not. */
    public function registry(): array
    {
        return [
            'deals' => [
                'label' => 'Deals', 'table' => 'deals', 'soft' => true, 'date' => 'deals.created_at',
                'dimensions' => [
                    'stage'  => ['label' => 'Stage',  'select' => 'ps.name', 'group' => 'ps.name',
                                 'join' => ['pipeline_stages as ps', 'ps.id', 'deals.stage_id']],
                    'owner'  => ['label' => 'Owner',  'select' => "COALESCE(u.name,'Unassigned')", 'group' => 'u.name',
                                 'join' => ['users as u', 'u.id', 'deals.owner_id']],
                    'status' => ['label' => 'Status', 'select' => 'deals.status', 'group' => 'deals.status'],
                    'month'  => ['label' => 'Month',  'select' => "DATE_FORMAT(deals.created_at,'%Y-%m')", 'group' => "DATE_FORMAT(deals.created_at,'%Y-%m')"],
                ],
                'measures' => [
                    'count'        => ['label' => 'Deals', 'select' => 'COUNT(*)'],
                    'total_amount' => ['label' => 'Total value', 'select' => 'ROUND(SUM(deals.amount),2)'],
                    'weighted'     => ['label' => 'Weighted', 'select' => 'ROUND(SUM(deals.amount*deals.probability/100),2)'],
                    'avg_amount'   => ['label' => 'Avg value', 'select' => 'ROUND(AVG(deals.amount),2)'],
                ],
                'filters' => ['status' => 'deals.status', 'owner_id' => 'deals.owner_id'],
            ],
            'invoices' => [
                'label' => 'Invoices', 'table' => 'invoices', 'soft' => true, 'date' => 'invoices.issue_date',
                'dimensions' => [
                    'status'   => ['label' => 'Status', 'select' => 'invoices.status', 'group' => 'invoices.status'],
                    'month'    => ['label' => 'Month', 'select' => "DATE_FORMAT(invoices.issue_date,'%Y-%m')", 'group' => "DATE_FORMAT(invoices.issue_date,'%Y-%m')"],
                    'customer' => ['label' => 'Customer', 'select' => "COALESCE(c.name,'—')", 'group' => 'c.name',
                                   'join' => ['customers as c', 'c.id', 'invoices.customer_id']],
                ],
                'measures' => [
                    'count'   => ['label' => 'Invoices', 'select' => 'COUNT(*)'],
                    'total'   => ['label' => 'Total', 'select' => 'ROUND(SUM(invoices.grand_total),2)'],
                    'paid'    => ['label' => 'Paid', 'select' => 'ROUND(SUM(invoices.amount_paid),2)'],
                    'balance' => ['label' => 'Outstanding', 'select' => 'ROUND(SUM(invoices.balance),2)'],
                ],
                'filters' => ['status' => 'invoices.status'],
            ],
            'leads' => [
                'label' => 'Leads', 'table' => 'leads', 'soft' => true, 'date' => 'leads.created_at',
                'dimensions' => [
                    'source' => ['label' => 'Source', 'select' => "COALESCE(ls.name,'—')", 'group' => 'ls.name',
                                 'join' => ['lead_sources as ls', 'ls.id', 'leads.source_id']],
                    'status' => ['label' => 'Status', 'select' => "COALESCE(lst.name,'—')", 'group' => 'lst.name',
                                 'join' => ['lead_statuses as lst', 'lst.id', 'leads.status_id']],
                    'rating' => ['label' => 'Rating', 'select' => 'leads.rating', 'group' => 'leads.rating'],
                    'owner'  => ['label' => 'Owner', 'select' => "COALESCE(u.name,'Unassigned')", 'group' => 'u.name',
                                 'join' => ['users as u', 'u.id', 'leads.owner_id']],
                ],
                'measures' => [
                    'count'       => ['label' => 'Leads', 'select' => 'COUNT(*)'],
                    'total_value' => ['label' => 'Pipeline value', 'select' => 'ROUND(SUM(leads.estimated_value),2)'],
                    'avg_score'   => ['label' => 'Avg score', 'select' => 'ROUND(AVG(leads.score),1)'],
                ],
                'filters' => ['rating' => 'leads.rating'],
            ],
            'tickets' => [
                'label' => 'Tickets', 'table' => 'tickets', 'soft' => true, 'date' => 'tickets.created_at',
                'dimensions' => [
                    'status'   => ['label' => 'Status', 'select' => 'tickets.status', 'group' => 'tickets.status'],
                    'priority' => ['label' => 'Priority', 'select' => 'tickets.priority', 'group' => 'tickets.priority'],
                    'category' => ['label' => 'Category', 'select' => "COALESCE(tc.name,'—')", 'group' => 'tc.name',
                                   'join' => ['ticket_categories as tc', 'tc.id', 'tickets.category_id']],
                    'assignee' => ['label' => 'Assignee', 'select' => "COALESCE(u.name,'Unassigned')", 'group' => 'u.name',
                                   'join' => ['users as u', 'u.id', 'tickets.assigned_to']],
                ],
                'measures' => ['count' => ['label' => 'Tickets', 'select' => 'COUNT(*)']],
                'filters' => ['status' => 'tickets.status', 'priority' => 'tickets.priority'],
            ],
        ];
    }

    /** Registry shape for the UI — labels only, no SQL. */
    public function datasetsMeta(): array
    {
        $out = [];
        foreach ($this->registry() as $key => $ds) {
            if (!Schema::hasTable($ds['table'])) continue;
            $out[] = [
                'key' => $key, 'label' => $ds['label'],
                'dimensions' => collect($ds['dimensions'])->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label']])->values(),
                'measures' => collect($ds['measures'])->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label']])->values(),
                'filters' => array_keys($ds['filters']),
            ];
        }
        return $out;
    }

    /**
     * Run a report spec and return { columns, rows }. Everything is validated against the
     * registry; values are bound.
     */
    public function run(string $dataset, ?string $dimension, array $measures, array $filters): array
    {
        $reg = $this->registry();
        if (!isset($reg[$dataset])) throw new RuntimeException('Unknown dataset.');
        $ds = $reg[$dataset];

        $dimension ??= array_key_first($ds['dimensions']);
        if (!isset($ds['dimensions'][$dimension])) throw new RuntimeException('Unknown dimension.');
        $dim = $ds['dimensions'][$dimension];

        // At least one measure; drop unknown keys.
        $measures = array_values(array_filter($measures, fn ($m) => isset($ds['measures'][$m])));
        if (!$measures) $measures = [array_key_first($ds['measures'])];

        $q = DB::table($ds['table'])->where($ds['table'].'.company_id', auth()->user()->company_id);
        if (!empty($ds['soft'])) $q->whereNull($ds['table'].'.deleted_at');
        if (!empty($dim['join'])) {
            [$joinTable, $left, $right] = $dim['join'];
            $q->leftJoin($joinTable, $left, '=', $right);
        }

        // Whitelisted filters, bound values.
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || !isset($ds['filters'][$key])) continue;
            $q->where($ds['filters'][$key], $value);
        }
        // Optional date range on the dataset's date column.
        if (!empty($filters['date_from'])) $q->whereDate($ds['date'], '>=', $filters['date_from']);
        if (!empty($filters['date_to']))   $q->whereDate($ds['date'], '<=', $filters['date_to']);

        $selects = [DB::raw($dim['select'].' as dimension')];
        foreach ($measures as $m) $selects[] = DB::raw($ds['measures'][$m]['select'].' as '.$m);

        $rows = $q->select($selects)->groupByRaw($dim['group'])->orderByRaw($dim['group'])->limit(1000)->get();

        $columns = array_merge(
            [['key' => 'dimension', 'label' => $dim['label']]],
            array_map(fn ($m) => ['key' => $m, 'label' => $ds['measures'][$m]['label']], $measures),
        );

        return [
            'dataset' => $dataset, 'dimension' => $dimension, 'measures' => $measures,
            'columns' => $columns,
            'rows' => $rows->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function runSaved(SavedReport $report): array
    {
        return $this->run($report->dataset, $report->dimension, $report->measures ?? [], $report->filters ?? []);
    }

    // ---- saved reports ---------------------------------------------------

    public function reports(): \Illuminate\Support\Collection
    {
        return SavedReport::with('creator:id,name')->orderBy('name')->get();
    }

    public function createReport(array $data): SavedReport
    {
        $data['created_by'] ??= auth()->id();
        return SavedReport::create($data);
    }

    public function updateReport(SavedReport $report, array $data): SavedReport
    {
        $report->update($data);
        return $report;
    }

    // ---- exports ---------------------------------------------------------

    /** Generate a CSV for a saved report and record the export. */
    public function export(SavedReport $report, string $format = 'csv'): ReportExport
    {
        $result = $this->runSaved($report);
        $companyId = $report->company_id;

        $export = ReportExport::create([
            'company_id' => $companyId, 'saved_report_id' => $report->id,
            'format' => 'csv', 'status' => 'pending', 'requested_by' => auth()->id(),
        ]);

        // Build CSV in memory (report row counts are small — capped at 1000 by run()).
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_map(fn ($c) => $c['label'], $result['columns']));
        foreach ($result['rows'] as $row) {
            fputcsv($handle, array_map(fn ($c) => $row[$c['key']] ?? '', $result['columns']));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // PRIVATE disk. These files were previously written to the public disk under
        // reports/Y/m/report-{reportId}-{exportId}.csv, and both ids are global
        // auto-increments — so anyone could walk report-{1..N}-{1..M} through the
        // public/storage symlink and read other tenants' exported aggregates with no
        // authentication at all, bypassing both permission:reports.export and the company
        // scope. The only access path now is ReportController::downloadExport, which resolves
        // the row through the company-scoped model first and streams by its stored path.
        // Same shape as the Documents library.
        $path = 'reports/'.$companyId.'/'.date('Y/m').'/report-'.$report->id.'-'.$export->id.'.csv';
        Storage::disk('local')->put($path, $csv);

        $export->forceFill([
            'status' => 'done', 'disk' => 'local', 'path' => $path,
            'row_count' => count($result['rows']), 'generated_at' => now(),
        ])->save();

        return $export;
    }

    public function exports(SavedReport $report): \Illuminate\Support\Collection
    {
        return $report->exports()->latest()->limit(20)->get();
    }
}
