<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class SlaCheckBreaches extends Command
{
    protected $signature = 'sla:check-breaches';
    protected $description = 'Check tickets for SLA breaches';
    public function handle(): int { return self::SUCCESS; }
}
