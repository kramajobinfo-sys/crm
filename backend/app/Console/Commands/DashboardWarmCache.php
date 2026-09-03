<?php
namespace App\Console\Commands;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Console\Command;
class DashboardWarmCache extends Command
{
    protected $signature = 'dashboard:warm-cache';
    protected $description = 'Pre-compute dashboard KPIs for all active users';
    public function handle(DashboardService $service): int
    {
        $count = 0;
        User::where('is_active', true)->chunkById(200, function ($users) use ($service, &$count) {
            foreach ($users as $user) {
                auth()->setUser($user);
                $service->kpis(); $service->salesChart(); $service->pipeline();
                $count++;
            }
        });
        $this->info("Warmed dashboard cache for {$count} users.");
        return self::SUCCESS;
    }
}
