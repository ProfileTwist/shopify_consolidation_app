<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Shopify and returns normalized orders + payouts.
 *
 * Drivers:
 *  - "mock": generates a coherent sample dataset (orders + Shopify Payments
 *            payouts whose transactions reconcile back to those orders) so the
 *            full pull -> store -> reconcile pipeline runs with no real store.
 *  - "http": Admin GraphQL for orders + Payments REST for payouts.
 *
 * Chosen from the connection's `auth_type` ('mock') else config('shopify.driver').
 */
class ShopifyClient
{
    protected function driver(Store $c): string
    {
        return $c->auth_type === 'mock' ? 'mock' : config('shopify.driver', 'mock');
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchOrders(Store $connection): array
    {
        return $this->driver($connection) === 'http'
            ? $this->fetchOrdersHttp($connection)
            : $this->mockOrders($connection);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchPayouts(Store $connection): array
    {
        // Payout data only exists when Shopify Payments is the processor.
        if (! $connection->payments_enabled) {
            return [];
        }

        return $this->driver($connection) === 'http'
            ? $this->fetchPayoutsHttp($connection)
            : $this->mockPayouts($connection);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchProducts(Store $connection): array
    {
        return $this->driver($connection) === 'http'
            ? $this->fetchProductsHttp($connection)
            : $this->mockProducts($connection);
    }

    // ---------------------------------------------------------------------
    // Real Shopify (structured; requires a live store's Admin API token).
    // ---------------------------------------------------------------------

    protected function baseUrl(Store $c): string
    {
        $version = config('shopify.api_version');
        return "https://{$c->shop_domain}/admin/api/{$version}";
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchOrdersHttp(Store $connection): array
    {
        // Admin REST: orders with the live status fields the proposal needs.
        $response = Http::withHeaders(['X-Shopify-Access-Token' => $connection->access_token])
            ->timeout(config('shopify.timeout'))
            ->acceptJson()
            ->get($this->baseUrl($connection) . '/orders.json', [
                'status' => 'any',
                'limit' => 250,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Shopify orders failed ({$response->status()}): " . $response->body());
        }

        return collect($response->json('orders', []))->map(function (array $o) {
            return [
                'shopify_id' => (string) ($o['id'] ?? ''),
                'order_number' => $o['name'] ?? null,
                'status' => $o['cancelled_at'] ?? null ? 'cancelled' : (($o['closed_at'] ?? null) ? 'closed' : 'open'),
                'financial_status' => $o['financial_status'] ?? null,
                'fulfillment_status' => $o['fulfillment_status'] ?? 'unfulfilled',
                'cancelled_at' => isset($o['cancelled_at']) ? $this->date($o['cancelled_at']) : null,
                'cancel_reason' => $o['cancel_reason'] ?? null,
                'account_name' => trim(($o['customer']['first_name'] ?? '') . ' ' . ($o['customer']['last_name'] ?? '')) ?: null,
                'customer_email' => $o['email'] ?? null,
                'currency' => $o['currency'] ?? 'USD',
                'total_amount' => (float) ($o['total_price'] ?? 0),
                'shopify_created_at' => $this->date($o['created_at'] ?? null),
                'shopify_updated_at' => $this->date($o['updated_at'] ?? null),
                'raw' => $o,
                'line_items' => collect($o['line_items'] ?? [])->map(fn ($li) => [
                    'shopify_id' => (string) ($li['id'] ?? ''),
                    'product_name' => $li['title'] ?? null,
                    'quantity' => (float) ($li['quantity'] ?? 0),
                    'unit_price' => (float) ($li['price'] ?? 0),
                    'total_price' => (float) ($li['price'] ?? 0) * (float) ($li['quantity'] ?? 0),
                    'raw' => $li,
                ])->all(),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchPayoutsHttp(Store $connection): array
    {
        $payoutsRes = Http::withHeaders(['X-Shopify-Access-Token' => $connection->access_token])
            ->timeout(config('shopify.timeout'))
            ->acceptJson()
            ->get($this->baseUrl($connection) . '/shopify_payments/payouts.json', ['limit' => 250]);

        if ($payoutsRes->failed()) {
            throw new RuntimeException("Shopify payouts failed ({$payoutsRes->status()}): " . $payoutsRes->body());
        }

        return collect($payoutsRes->json('payouts', []))->map(function (array $p) use ($connection) {
            $summary = $p['summary'] ?? [];
            return [
                'external_id' => (string) ($p['id'] ?? ''),
                'status' => $p['status'] ?? null,
                'issued_at' => $this->date($p['date'] ?? null),
                'currency' => $p['currency'] ?? 'USD',
                'amount' => (float) ($p['amount'] ?? 0),
                'gross' => (float) ($summary['charges_gross_amount'] ?? 0),
                'fees' => (float) ($summary['charges_fee_amount'] ?? 0),
                'refunds' => (float) ($summary['refunds_fee_amount'] ?? 0),
                'adjustments' => (float) ($summary['adjustments_gross_amount'] ?? 0),
                'reserved' => (float) ($summary['reserved_funds_gross_amount'] ?? 0),
                'raw' => $p,
                // Transaction detail lives at /shopify_payments/balance/transactions.json?payout_id=
                'transactions' => $this->fetchPayoutTransactionsHttp($connection, (string) ($p['id'] ?? '')),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchPayoutTransactionsHttp(Store $connection, string $payoutId): array
    {
        $res = Http::withHeaders(['X-Shopify-Access-Token' => $connection->access_token])
            ->timeout(config('shopify.timeout'))
            ->acceptJson()
            ->get($this->baseUrl($connection) . '/shopify_payments/balance/transactions.json', [
                'payout_id' => $payoutId,
            ]);

        if ($res->failed()) {
            return [];
        }

        return collect($res->json('transactions', []))->map(fn ($t) => [
            'external_id' => (string) ($t['id'] ?? ''),
            'type' => $t['type'] ?? null,
            'amount' => (float) ($t['amount'] ?? 0),
            'fee' => (float) ($t['fee'] ?? 0),
            'net' => (float) ($t['net'] ?? 0),
            'source_order_external_id' => isset($t['source_order_id']) ? (string) $t['source_order_id'] : null,
            'raw' => $t,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchProductsHttp(Store $connection): array
    {
        $response = Http::withHeaders(['X-Shopify-Access-Token' => $connection->access_token])
            ->timeout(config('shopify.timeout'))
            ->acceptJson()
            ->get($this->baseUrl($connection) . '/products.json', ['limit' => 250]);

        if ($response->failed()) {
            throw new RuntimeException("Shopify products failed ({$response->status()}): " . $response->body());
        }

        return collect($response->json('products', []))->map(function (array $p) {
            $variants = $p['variants'] ?? [];
            $prices = array_map(fn ($v) => (float) ($v['price'] ?? 0), $variants);
            $inventory = array_sum(array_map(fn ($v) => (int) ($v['inventory_quantity'] ?? 0), $variants));

            return [
                'shopify_id' => (string) ($p['id'] ?? ''),
                'title' => $p['title'] ?? null,
                'vendor' => $p['vendor'] ?? null,
                'product_type' => $p['product_type'] ?? null,
                'status' => $p['status'] ?? null,
                'handle' => $p['handle'] ?? null,
                'variants_count' => count($variants),
                'total_inventory' => $inventory,
                'price_min' => $prices ? min($prices) : 0,
                'price_max' => $prices ? max($prices) : 0,
                'image_url' => data_get($p, 'image.src') ?? data_get($p, 'images.0.src'),
                'shopify_created_at' => $this->date($p['created_at'] ?? null),
                'raw' => $p,
            ];
        })->all();
    }

    protected function date(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    // ---------------------------------------------------------------------
    // Mock dataset (deterministic order ids so payouts can reconcile to them).
    // ---------------------------------------------------------------------

    protected function orderExternalId(Store $c, int $i): string
    {
        return sprintf('gid-%d-%04d', $c->id, $i);
    }

    protected function orderCount(): int
    {
        return (int) config('shopify.mock_orders_per_sync', 25);
    }

    /** @return array<int, array<string, mixed>> */
    protected function mockOrders(Store $connection): array
    {
        // Deterministic per connection so re-syncing produces identical data
        // (mt_srand seeds mt_rand/array_rand) — real Shopify records are stable too.
        mt_srand($connection->id * 1000 + 1);

        $financial = ['paid', 'paid', 'paid', 'pending', 'partially_refunded', 'refunded', 'voided'];
        $fulfillment = ['fulfilled', 'fulfilled', 'partial', 'unfulfilled'];
        $products = ['Cabernet Sauvignon', 'Kentucky Bourbon', 'Hazy IPA 6pk', 'Silver Tequila', 'Prosecco DOC', 'Single Malt Scotch'];
        $orders = [];

        for ($i = 1; $i <= $this->orderCount(); $i++) {
            $lineCount = mt_rand(1, 4);
            $lines = [];
            $total = 0.0;
            for ($l = 1; $l <= $lineCount; $l++) {
                $qty = mt_rand(1, 12);
                $unit = round(mt_rand(899, 8999) / 100, 2);
                $lineTotal = round($qty * $unit, 2);
                $total += $lineTotal;
                $lines[] = [
                    'shopify_id' => $this->orderExternalId($connection, $i) . "-L{$l}",
                    'product_name' => $products[array_rand($products)],
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total_price' => $lineTotal,
                    'raw' => [],
                ];
            }

            $isCancelled = mt_rand(1, 12) === 1;
            $createdAt = Carbon::now()->subDays(mt_rand(1, 120));

            $orders[] = [
                'shopify_id' => $this->orderExternalId($connection, $i),
                'order_number' => '#' . (1000 + $i),
                'status' => $isCancelled ? 'cancelled' : (mt_rand(0, 3) === 0 ? 'closed' : 'open'),
                'financial_status' => $financial[array_rand($financial)],
                'fulfillment_status' => $fulfillment[array_rand($fulfillment)],
                'cancelled_at' => $isCancelled ? $createdAt->copy()->addDays(1)->toDateTimeString() : null,
                'cancel_reason' => $isCancelled ? ['customer', 'inventory', 'fraud', 'declined'][array_rand(['a', 'b', 'c', 'd'])] : null,
                'account_name' => "Customer {$i}",
                'customer_email' => "customer{$i}@example.test",
                'currency' => 'USD',
                'total_amount' => round($total, 2),
                'shopify_created_at' => $createdAt->toDateTimeString(),
                'shopify_updated_at' => $createdAt->copy()->addDays(mt_rand(0, 5))->toDateTimeString(),
                'raw' => ['mock' => true, 'shop' => $connection->shop_domain],
                'line_items' => $lines,
            ];
        }

        return $orders;
    }

    /** @return array<int, array<string, mixed>> */
    protected function mockPayouts(Store $connection): array
    {
        // Deterministic per connection so re-syncing upserts the same payouts +
        // transactions instead of accumulating new random ones.
        mt_srand($connection->id * 1000 + 2);

        $statuses = ['paid', 'paid', 'paid', 'in_transit', 'scheduled'];
        $payouts = [];
        $orderPool = range(1, $this->orderCount());
        shuffle($orderPool);
        $cursor = 0;
        $payoutCount = 4;

        for ($p = 1; $p <= $payoutCount; $p++) {
            $ordersInPayout = array_slice($orderPool, $cursor, mt_rand(3, 6));
            $cursor += count($ordersInPayout);
            if (empty($ordersInPayout)) {
                break;
            }

            $transactions = [];
            $gross = 0.0;
            $feeTotal = 0.0;
            $refundTotal = 0.0;

            foreach ($ordersInPayout as $orderIdx) {
                $isRefund = mt_rand(1, 8) === 1;
                $charge = round(mt_rand(2500, 45000) / 100, 2);
                $fee = round($charge * 0.029 + 0.30, 2); // Shopify Payments 2.9% + 30c
                if ($isRefund) {
                    $refundTotal += $charge;
                    $transactions[] = [
                        'external_id' => "bt-{$connection->id}-{$p}-{$orderIdx}",
                        'type' => 'refund',
                        'amount' => -$charge,
                        'fee' => 0,
                        'net' => -$charge,
                        'source_order_external_id' => $this->orderExternalId($connection, $orderIdx),
                        'raw' => [],
                    ];
                } else {
                    $gross += $charge;
                    $feeTotal += $fee;
                    $transactions[] = [
                        'external_id' => "bt-{$connection->id}-{$p}-{$orderIdx}",
                        'type' => 'charge',
                        'amount' => $charge,
                        'fee' => $fee,
                        'net' => round($charge - $fee, 2),
                        'source_order_external_id' => $this->orderExternalId($connection, $orderIdx),
                        'raw' => [],
                    ];
                }
            }

            $net = round($gross - $feeTotal - $refundTotal, 2);
            $issuedAt = Carbon::now()->subDays($payoutCount - $p + 1);

            $payouts[] = [
                'external_id' => "po-{$connection->id}-{$p}",
                'status' => $statuses[array_rand($statuses)],
                'issued_at' => $issuedAt->toDateString(),
                'currency' => 'USD',
                'amount' => $net,
                'gross' => round($gross, 2),
                'fees' => round($feeTotal, 2),
                'refunds' => round($refundTotal, 2),
                'adjustments' => 0,
                'reserved' => 0,
                'raw' => ['mock' => true],
                'transactions' => $transactions,
            ];
        }

        return $payouts;
    }

    /** @return array<int, array<string, mixed>> */
    protected function mockProducts(Store $connection): array
    {
        mt_srand($connection->id * 1000 + 3);

        $catalog = [
            ['Cabernet Sauvignon', 'Napa Cellars', 'Wine'],
            ['Kentucky Straight Bourbon', 'Old Barrel Co', 'Whiskey'],
            ['Hazy IPA 6-pack', 'Coastal Brewing', 'Beer'],
            ['Silver Tequila', 'Agave Sol', 'Tequila'],
            ['Prosecco DOC', 'Veneto Vines', 'Wine'],
            ['Single Malt Scotch', 'Highland Reserve', 'Whiskey'],
            ['London Dry Gin', 'Botanica', 'Gin'],
            ['Spiced Rum', 'Islander', 'Rum'],
        ];

        $products = [];
        foreach ($catalog as $i => [$title, $vendor, $type]) {
            $variants = mt_rand(1, 3);
            $min = round(mt_rand(899, 4999) / 100, 2);
            $products[] = [
                'shopify_id' => sprintf('prod-%d-%03d', $connection->id, $i + 1),
                'title' => $title,
                'vendor' => $vendor,
                'product_type' => $type,
                'status' => 'active',
                'handle' => str($title)->slug()->value(),
                'variants_count' => $variants,
                'total_inventory' => mt_rand(0, 240),
                'price_min' => $min,
                'price_max' => round($min + mt_rand(0, 3000) / 100, 2),
                'image_url' => null,
                'shopify_created_at' => now()->subDays(mt_rand(10, 300))->toDateTimeString(),
                'raw' => ['mock' => true],
            ];
        }

        return $products;
    }
}
