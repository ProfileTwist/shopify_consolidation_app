<?php

namespace App\Http\Controllers;

use App\Jobs\PullShopifyDataJob;
use App\Models\Store;
use App\Services\Shopify\ShopifyOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-click "Connect a Shopify store".
 *
 * connect():  the admin enters a shop domain. If the OAuth app is configured we
 *             return the Shopify authorize URL for the browser to redirect to;
 *             otherwise we connect instantly in demo mode (sample data).
 * callback(): Shopify redirects here after the merchant approves — we verify the
 *             HMAC, exchange the code for a token, save the store, register
 *             webhooks, kick off the first sync, and bounce back to the SPA.
 */
class ShopifyConnectController extends Controller
{
    public function __construct(private ShopifyOAuth $oauth)
    {
    }

    public function connect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_domain' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'sync_interval_seconds' => ['nullable', 'integer', 'min:5', 'max:86400'],
        ]);

        $shop = $this->oauth->normalizeDomain($data['shop_domain']);
        if (! $shop) {
            return response()->json([
                'message' => 'Enter a valid Shopify domain, e.g. mystore.myshopify.com',
            ], 422);
        }

        $interval = $data['sync_interval_seconds'] ?? 60;
        $name = ($data['name'] ?? null) ?: $this->nameFromDomain($shop);

        // Custom-app token path: connect a single store directly with its Admin
        // API access token (no OAuth app needed). Token is encrypted at rest.
        if (! empty($data['access_token'])) {
            $store = Store::updateOrCreate(
                ['shop_domain' => $shop],
                [
                    'name' => $name,
                    'platform' => 'shopify',
                    'auth_type' => 'token',
                    'access_token' => $data['access_token'],
                    'is_active' => true,
                    'status' => 'pending',
                    'payments_enabled' => true,
                    'sync_interval_seconds' => $interval,
                ]
            );

            PullShopifyDataJob::dispatch($store->id);

            return response()->json([
                'mode' => 'token',
                'message' => "Connected {$name} with access token. Syncing…",
            ]);
        }

        // Live OAuth install when the app is configured.
        if ($this->oauth->isConfigured()) {
            $state = $this->oauth->newState();
            Cache::put("shopify-oauth:{$state}", [
                'shop' => $shop,
                'name' => $name,
                'interval' => $interval,
            ], now()->addMinutes(10));

            return response()->json([
                'mode' => 'oauth',
                'url' => $this->oauth->buildAuthorizeUrl($shop, $state),
            ]);
        }

        // Demo fallback: connect instantly with sample data.
        if (! config('shopify.allow_demo')) {
            return response()->json([
                'message' => 'Shopify app is not configured. Set SHOPIFY_CLIENT_ID / SHOPIFY_CLIENT_SECRET.',
            ], 422);
        }

        $store = Store::updateOrCreate(
            ['shop_domain' => $shop],
            [
                'name' => $name,
                'platform' => 'shopify',
                'auth_type' => 'mock',
                'is_active' => true,
                'status' => 'pending',
                'payments_enabled' => true,
                'sync_interval_seconds' => $interval,
            ]
        );

        PullShopifyDataJob::dispatch($store->id);

        return response()->json([
            'mode' => 'demo',
            'message' => "Connected {$name} (demo data). Syncing…",
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontend = rtrim(config('shopify.frontend_url'), '/');

        try {
            if (! $this->oauth->verifyHmac($request->query())) {
                return redirect()->away("{$frontend}/connections?connected=0&error=bad_signature");
            }

            $state = (string) $request->query('state');
            $cached = Cache::pull("shopify-oauth:{$state}");
            $shop = $this->oauth->normalizeDomain((string) $request->query('shop'));

            if (! $cached || ! $shop || $cached['shop'] !== $shop) {
                return redirect()->away("{$frontend}/connections?connected=0&error=bad_state");
            }

            $token = $this->oauth->exchangeCodeForToken($shop, (string) $request->query('code'));

            $store = Store::updateOrCreate(
                ['shop_domain' => $shop],
                [
                    'name' => $cached['name'],
                    'platform' => 'shopify',
                    'auth_type' => 'oauth',
                    'access_token' => $token,
                    'is_active' => true,
                    'status' => 'pending',
                    'payments_enabled' => true,
                    'sync_interval_seconds' => $cached['interval'],
                ]
            );

            $this->oauth->registerWebhooks($shop, $token);
            PullShopifyDataJob::dispatch($store->id);

            return redirect()->away("{$frontend}/connections?connected=1&shop={$shop}");
        } catch (Throwable $e) {
            report($e);
            return redirect()->away("{$frontend}/connections?connected=0&error=install_failed");
        }
    }

    private function nameFromDomain(string $shop): string
    {
        $handle = Str::before($shop, '.myshopify.com');
        return Str::of($handle)->replace('-', ' ')->title()->value();
    }
}
