<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo sales quotas (Zoho gap #10 — Forecasts). Sets this month's and this quarter's targets for
 * the seeded sales users; the forecast reads closed-won/pipeline live off deals. Idempotent.
 */
class ForecastSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $targets = [
            'sales.mgr@krama.local' => ['month' => 250000, 'quarter' => 750000],
            'sales@krama.local'     => ['month' => 120000, 'quarter' => 360000],
        ];
        $month   = Carbon::now()->startOfMonth()->toDateString();
        $quarter = Carbon::now()->startOfQuarter()->toDateString();

        foreach ($targets as $email => $amts) {
            $uid = User::where('company_id', $cid)->where('email', $email)->value('id');
            if (!$uid) continue;
            foreach (['month' => $month, 'quarter' => $quarter] as $type => $start) {
                SalesTarget::updateOrCreate(
                    ['company_id' => $cid, 'user_id' => $uid, 'period_type' => $type, 'period_start' => $start],
                    ['target_amount' => $amts[$type]]
                );
            }
        }
    }
}
