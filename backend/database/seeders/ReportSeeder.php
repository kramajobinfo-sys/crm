<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $ceo = User::where('company_id', $cid)->where('email', 'ceo@krama.local')->value('id');

        $reports = [
            ['Pipeline by stage','How deals are distributed across stages','deals','stage',['count','total_amount','weighted'],[],'bar'],
            ['Revenue by month','Invoiced totals over time','invoices','month',['total','paid','balance'],[],'line'],
            ['Leads by source','Which channels bring the most leads','leads','source',['count','total_value'],[],'pie'],
            ['Open tickets by priority','Support load by urgency','tickets','priority',['count'],['status' => 'open'],'bar'],
        ];
        $ids = [];
        foreach ($reports as [$name,$desc,$dataset,$dim,$measures,$filters,$chart]) {
            $r = SavedReport::updateOrCreate(
                ['company_id' => $cid, 'name' => $name],
                ['description' => $desc, 'dataset' => $dataset, 'dimension' => $dim,
                 'measures' => $measures, 'filters' => $filters ?: null, 'chart_type' => $chart,
                 'is_shared' => true, 'created_by' => $ceo]
            );
            $ids[] = $r->id;
        }

        Dashboard::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Executive overview'],
            ['layout' => array_map(fn ($id) => ['report_id' => $id, 'size' => 'half'], array_slice($ids, 0, 4)),
             'is_default' => true, 'created_by' => $ceo]
        );

        $this->command?->info('Seeded '.count($reports).' saved reports, 1 dashboard.');
    }
}
