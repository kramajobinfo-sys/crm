<?php
namespace App\Console\Commands;

use App\Services\HelpdeskService;
use Illuminate\Console\Command;

class SlaCheckBreaches extends Command
{
    protected $signature = 'sla:check-breaches';
    protected $description = 'Flag tickets whose first-response or resolution SLA has breached, and notify assignees';

    public function handle(HelpdeskService $helpdesk): int
    {
        $r = $helpdesk->sweepSlaBreaches();
        $this->info("SLA sweep: {$r['response']} first-response breaches, {$r['resolution']} resolution breaches flagged.");
        return self::SUCCESS;
    }
}
