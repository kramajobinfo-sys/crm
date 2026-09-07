<?php
namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a committed import batch off the request path. tries=1 so a partially-applied import is
 * never silently re-run; ImportService::process records per-row errors and finalizes the batch.
 */
class RunImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    public function handle(ImportService $imports): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->find($this->batchId);
        if ($batch && $batch->status === 'processing') {
            $imports->process($batch);
        }
    }
}
