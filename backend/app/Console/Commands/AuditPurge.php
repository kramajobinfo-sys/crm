<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class AuditPurge extends Command
{
    protected $signature = 'audit:purge {--days=365}';
    protected $description = 'Delete audit_logs older than N days';
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = DB::table('audit_logs')->where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Purged {$deleted} audit log entries older than {$days} days.");
        return self::SUCCESS;
    }
}
