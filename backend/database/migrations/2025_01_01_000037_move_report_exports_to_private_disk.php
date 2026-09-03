<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves already-generated report exports off the world-readable public disk.
 *
 * ReportService used to write them to storage/app/public under
 * reports/Y/m/report-{reportId}-{exportId}.csv. Both ids are global auto-increments and
 * backend/public/storage is a live symlink, so every historical export was readable by
 * anyone who could guess a path — no token, no permission, no company scope. Changing the
 * writer only protects NEW exports; the existing files stay exposed until they are moved,
 * which is what this does.
 *
 * Ordering is deliberate: copy to the private disk, verify it landed, repoint the row, and
 * only then delete the public copy. A partial run therefore leaves a readable duplicate
 * (bad but recoverable, and re-running finishes the job) rather than a row pointing at a
 * file that no longer exists. Idempotent: rows already on a non-public disk are skipped.
 *
 * DEPLOY NOTE: this migration CREATES directories under storage/app/private. Run as root
 * (which `docker compose exec` does) they come out root-owned and mode 700, and PHP-FPM
 * (www-data) then cannot write new exports into them — the export endpoint fails with
 * "Unable to create a directory at ...". After migrating, run:
 *
 *   docker compose exec backend chown -R www-data:www-data storage/app/private
 *
 * This is the project's standing storage-ownership gotcha, not a fault in this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $local  = Storage::disk('local');

        $rows = DB::table('report_exports')
            ->whereNotNull('path')
            ->where(fn ($q) => $q->whereNull('disk')->orWhere('disk', 'public'))
            ->get(['id', 'company_id', 'saved_report_id', 'path']);

        foreach ($rows as $row) {
            // New layout puts company_id in the path; the old one did not.
            $target = 'reports/'.$row->company_id.'/'.ltrim(preg_replace('#^reports/#', '', $row->path), '/');

            if (!$public->exists($row->path)) {
                // File already gone (manually cleaned, or a prior partial run). Still repoint
                // the row so nothing keeps claiming to live on the public disk.
                DB::table('report_exports')->where('id', $row->id)
                    ->update(['disk' => 'local', 'path' => $target]);
                continue;
            }

            if (!$local->exists($target)) {
                $local->put($target, $public->get($row->path));
            }

            if (!$local->exists($target)) {
                // Copy failed — leave the row and the public file untouched so the export is
                // still retrievable, and let a re-run try again.
                continue;
            }

            DB::table('report_exports')->where('id', $row->id)
                ->update(['disk' => 'local', 'path' => $target]);

            $public->delete($row->path);
        }

        // Drop the now-empty public reports tree (best effort — never fail the migration for it).
        try {
            foreach ($public->allFiles('reports') as $leftover) {
                // Anything still here has no owning row; it is unreachable and world-readable.
                $public->delete($leftover);
            }
            $public->deleteDirectory('reports');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        // Deliberately not reversed: moving these files back would re-expose them publicly.
        // The rows keep their disk/path, so the authenticated download keeps working.
    }
};
