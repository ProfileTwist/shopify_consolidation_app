<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\SalesOrder;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls orders + payouts for a single Shopify store and upserts them.
 *
 * Isolation: one store's failure is recorded on that store only. Idempotency:
 * every upsert is keyed on (connection/payout, external id), so re-running never
 * duplicates. Payout transactions are reconciled back to order rows by matching
 * source_order_external_id -> sales_orders.shopify_id.
 */
class PullShopifyDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $connectionId)
    {
    }

    public function handle(ShopifyClient $client): void
    {
        $connection = Store::find($this->connectionId);

        if (! $connection || ! $connection->is_active) {
            return;
        }

        $run = $connection->syncRuns()->create([
            'status' => 'running',
            'started_at' => Carbon::now(),
        ]);

        try {
            $orderCount = $this->syncOrders($client, $connection);
            $this->syncPayouts($client, $connection);
            $this->syncProducts($client, $connection);

            $connection->update([
                'status' => 'healthy',
                'last_synced_at' => Carbon::now(),
                'last_error' => null,
            ]);

            $run->update([
                'status' => 'success',
                'orders_synced' => $orderCount,
                'finished_at' => Carbon::now(),
                'message' => "Synced {$orderCount} orders + payouts.",
            ]);
        } catch (Throwable $e) {
            Log::error('Shopify sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);
            $run->update([
                'status' => 'failed',
                'finished_at' => Carbon::now(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function syncOrders(ShopifyClient $client, Store $connection): int
    {
        $count = 0;

        foreach ($client->fetchOrders($connection) as $order) {
            DB::transaction(function () use ($connection, $order, &$count) {
                $lineItems = $order['line_items'] ?? [];
                unset($order['line_items']);

                $model = SalesOrder::updateOrCreate(
                    ['store_id' => $connection->id, 'shopify_id' => $order['shopify_id']],
                    $order
                );

                foreach ($lineItems as $item) {
                    $model->lineItems()->updateOrCreate(['shopify_id' => $item['shopify_id']], $item);
                }

                $count++;
            });
        }

        return $count;
    }

    protected function syncProducts(ShopifyClient $client, Store $connection): void
    {
        // Products are additive: if the token lacks read_products, skip rather
        // than failing the whole sync (orders + payouts still succeed).
        try {
            foreach ($client->fetchProducts($connection) as $product) {
                Product::updateOrCreate(
                    ['store_id' => $connection->id, 'shopify_id' => $product['shopify_id']],
                    $product + ['store_id' => $connection->id]
                );
            }
        } catch (Throwable $e) {
            Log::warning('Product sync skipped (missing scope?)', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function syncPayouts(ShopifyClient $client, Store $connection): void
    {
        // Map this store's order external ids -> local order ids for reconciliation.
        $orderMap = SalesOrder::where('store_id', $connection->id)
            ->pluck('id', 'shopify_id');

        foreach ($client->fetchPayouts($connection) as $payout) {
            DB::transaction(function () use ($connection, $payout, $orderMap) {
                $transactions = $payout['transactions'] ?? [];
                unset($payout['transactions']);

                /** @var Payout $model */
                $model = $connection->payouts()->updateOrCreate(
                    ['external_id' => $payout['external_id']],
                    $payout
                );

                foreach ($transactions as $txn) {
                    $txn['sales_order_id'] = $orderMap[$txn['source_order_external_id'] ?? null] ?? null;
                    $model->transactions()->updateOrCreate(
                        ['external_id' => $txn['external_id']],
                        $txn
                    );
                }
            });
        }
    }
}
