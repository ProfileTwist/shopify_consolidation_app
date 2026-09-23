<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Seeds fake orders INTO a connected Shopify store (dev/test stores only).
 *
 * Uses the store's Admin API token to create draft orders with custom line
 * items, then completes each one (payment_pending=false) so it becomes a real,
 * paid order in Shopify — which the consolidator then pulls on its next sync.
 *
 * Requires the token to have the `write_draft_orders` scope.
 */
class SeedShopifyStoreCommand extends Command
{
    protected $signature = 'shopify:seed-store
        {--store= : Store id to seed}
        {--count=15 : How many orders to create}
        {--products=8 : How many catalog products to create}';

    protected $description = 'Create fake products + completed orders in a connected Shopify store (dev stores only)';

    public function handle(): int
    {
        $store = Store::find($this->option('store'));
        if (! $store) {
            $this->error('Store not found. Pass --store=<id>.');
            return self::FAILURE;
        }
        if ($store->auth_type === 'mock' || ! $store->access_token) {
            $this->error('Store has no real Admin API token (mock or not connected).');
            return self::FAILURE;
        }

        $version = config('shopify.api_version');
        $base = "https://{$store->shop_domain}/admin/api/{$version}";
        $count = (int) $this->option('count');

        $products = ['Cabernet Sauvignon', 'Kentucky Bourbon', 'Hazy IPA 6-pack', 'Silver Tequila', 'Prosecco DOC', 'Single Malt Scotch'];

        // 0. Seed catalog products (needs write_products).
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
        $productCount = min((int) $this->option('products'), count($catalog));
        for ($p = 0; $p < $productCount; $p++) {
            [$title, $vendor, $type] = $catalog[$p];
            $res = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
                ->timeout(config('shopify.timeout'))
                ->post("{$base}/products.json", [
                    'product' => [
                        'title' => $title,
                        'vendor' => $vendor,
                        'product_type' => $type,
                        'status' => 'active',
                        'variants' => [[
                            'price' => (string) round(random_int(899, 6999) / 100, 2),
                            'inventory_quantity' => random_int(0, 200),
                        ]],
                    ],
                ]);
            if ($res->successful()) {
                $this->line("Created product: {$title}");
            } elseif ($res->status() === 403) {
                $this->warn('403 on products → token lacks write_products; skipping product seeding.');
                break;
            } else {
                $this->error("Product '{$title}' failed ({$res->status()}): " . $res->body());
            }
        }

        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $lineItems = [];
            $lineCount = random_int(1, 4);
            for ($l = 0; $l < $lineCount; $l++) {
                $lineItems[] = [
                    'title' => $products[array_rand($products)],
                    'price' => (string) round(random_int(899, 8999) / 100, 2),
                    'quantity' => random_int(1, 6),
                ];
            }

            // 1. Create the draft order.
            $draftRes = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
                ->timeout(config('shopify.timeout'))
                ->post("{$base}/draft_orders.json", [
                    'draft_order' => [
                        'line_items' => $lineItems,
                        'email' => "customer{$i}@example.com",
                        'tags' => 'seed',
                    ],
                ]);

            if ($draftRes->failed()) {
                $this->error("Draft {$i} failed ({$draftRes->status()}): " . $draftRes->body());
                if ($draftRes->status() === 403) {
                    $this->warn('403 → token likely lacks write_draft_orders. Reconnect the store to grant it.');
                    return self::FAILURE;
                }
                continue;
            }

            $draftId = $draftRes->json('draft_order.id');

            // 2. Complete it (payment_pending=false → marks the order paid).
            $completeRes = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
                ->timeout(config('shopify.timeout'))
                ->put("{$base}/draft_orders/{$draftId}/complete.json", [
                    'payment_pending' => false,
                ]);

            if ($completeRes->failed()) {
                $this->error("Complete {$i} failed ({$completeRes->status()}): " . $completeRes->body());
                continue;
            }

            $created++;
            $this->line("Created order {$created}/{$count} (draft {$draftId})");
        }

        $this->info("Done — created {$created} order(s) in {$store->shop_domain}. Run a sync to pull them.");

        return self::SUCCESS;
    }
}
