<?php
namespace App\Console\Commands;

use App\Services\LeadService;
use Illuminate\Console\Command;

class NotifyFollowUpsDue extends Command
{
    protected $signature = 'leads:notify-follow-ups';
    protected $description = 'Notify owners of open leads whose follow-up date has arrived';

    public function handle(LeadService $leads): int
    {
        $n = $leads->sweepFollowUpsDue();
        $this->info("Follow-up sweep: {$n} lead(s) notified.");
        return self::SUCCESS;
    }
}
