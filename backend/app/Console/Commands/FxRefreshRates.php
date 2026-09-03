<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class FxRefreshRates extends Command
{
    protected $signature = 'fx:refresh-rates';
    protected $description = 'Refresh FX rates from provider';
    public function handle(): int { $this->info('FX refresh stub — implement when provider selected.'); return self::SUCCESS; }
}
