<?php

namespace App\Console\Commands;

use App\Jobs\PullShopifyDataJob;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncStoresCommand extends Command
{
    protected $signature = 'shopify:sync
        {--store= : Only sync this store id}';

    protected $description = 'Dispatch order + payout pull jobs for active Shopify stores';

    public function handle(): int
    {
        $query = Store::query()->where('is_active', true);

        if ($id = $this->option('store')) {
            $query->whereKey($id);
        }

        $stores = $query->get();

        if ($stores->isEmpty()) {
            $this->warn('No active stores to sync.');
            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            PullShopifyDataJob::dispatch($store->id);
            $this->info("Dispatched sync for [{$store->id}] {$store->name}");
        }

        $this->info("Dispatched {$stores->count()} sync job(s).");

        return self::SUCCESS;
    }
}
