<?php

namespace App\Console\Commands;

use App\Jobs\PullShopifyDataJob;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Per-connection job runner.
 *
 * Runs frequently (sub-minute, via schedule:work) and dispatches a sync job for
 * each active connection whose own interval has elapsed. A per-connection cache
 * gate (TTL = the connection's interval) guarantees we dispatch at most once per
 * interval regardless of how often this command ticks or how long a job runs.
 */
class SyncDueConnectionsCommand extends Command
{
    protected $signature = 'sync:due';

    protected $description = 'Dispatch sync jobs for connections whose interval has elapsed';

    public function handle(): int
    {
        $connections = Store::where('is_active', true)->get();
        $dispatched = 0;

        foreach ($connections as $connection) {
            $interval = max(5, (int) $connection->sync_interval_seconds);
            $gate = "sync-due:{$connection->id}";

            // Cache::add only succeeds if the key is absent -> once per interval.
            if (! Cache::add($gate, true, $interval)) {
                continue;
            }

            PullShopifyDataJob::dispatch($connection->id);

            $dispatched++;
            $this->line("Dispatched sync for [{$connection->id}] {$connection->name} (every {$interval}s)");
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} due connection(s).");
        }

        return self::SUCCESS;
    }
}
