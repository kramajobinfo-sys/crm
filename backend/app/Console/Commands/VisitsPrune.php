<?php
namespace App\Console\Commands;

use App\Services\VisitService;
use Illuminate\Console\Command;

/**
 * Page views grow without bound — the single most common latent problem the cross-module
 * audit found. Scheduled daily next to audit:purge.
 *
 * Runs unauthenticated: VisitService::prune() therefore never touches auth(), never infers
 * company_id, and uses withoutGlobalScope('company') rather than the plural form.
 */
class VisitsPrune extends Command
{
    protected $signature = 'visits:prune {--days= : Override VISITS_RETENTION_DAYS}';
    protected $description = 'Delete website page views past the retention window, and the anonymous visitors left empty';

    public function handle(VisitService $visits): int
    {
        $days = (int) ($this->option('days') ?: config('visits.retention_days'));
        if ($days < 1) {
            $this->error('Retention must be at least 1 day.');
            return self::FAILURE;
        }

        $result = $visits->prune($days);
        $this->info("Pruned {$result['page_views']} page view(s) and {$result['visitors']} anonymous visitor(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
