<?php
namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadScoringService;
use Illuminate\Console\Command;

class LeadsScore extends Command
{
    protected $signature = 'leads:score {--company= : Limit to one company id}';
    protected $description = 'Recalculate lead scores';

    public function handle(LeadScoringService $scoring): int
    {
        // Runs unauthenticated on the scheduler, so the BelongsToCompany global scope
        // must be bypassed explicitly — otherwise it silently scores nothing.
        $query = Lead::withoutGlobalScopes()
            ->with('status:id,is_won,is_lost')
            ->whereNull('converted_to_customer_id')
            ->whereNull('deleted_at');

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $changed = 0;
        $seen = 0;

        $query->chunkById(500, function ($leads) use ($scoring, &$changed, &$seen) {
            foreach ($leads as $lead) {
                $seen++;
                $before = [$lead->score, $lead->rating];
                $result = $scoring->evaluate($lead);
                if ([$result['score'], $result['rating']] !== $before) {
                    $lead->forceFill(['score' => $result['score'], 'rating' => $result['rating']])->saveQuietly();
                    $changed++;
                }
            }
        });

        $this->info("Scored {$seen} open leads; {$changed} changed.");
        return self::SUCCESS;
    }
}
