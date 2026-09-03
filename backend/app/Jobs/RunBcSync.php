<?php
namespace App\Jobs;

use App\Models\BcEntityMapping;
use App\Services\Dynamics\SyncEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBcSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;          // the engine records failures itself; retrying would duplicate runs
    public int $timeout = 300;

    public function __construct(
        public readonly int $mappingId,
        public readonly string $trigger = 'manual',
    ) {
        // Must be a queue the worker actually consumes — see docker-compose.yml.
        $this->onQueue(config('dynamics.queue'));
    }

    public function handle(SyncEngine $engine): void
    {
        $mapping = BcEntityMapping::withoutGlobalScopes()->with('connection')->find($this->mappingId);
        if (!$mapping || !$mapping->connection) return;   // deleted between dispatch and run

        $engine->run($mapping, $this->trigger);
    }
}
