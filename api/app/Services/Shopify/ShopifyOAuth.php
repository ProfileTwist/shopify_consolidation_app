<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles the Shopify OAuth "install" handshake used by the one-click connect:
 *
 *   1. buildAuthorizeUrl()  -> redirect merchant to Shopify to approve scopes
 *   2. (Shopify redirects back with code + hmac + shop)
 *   3. verifyHmac()         -> confirm the callback really came from Shopify
 *   4. exchangeCodeForToken -> swap the code for a permanent Admin API token
 *   5. registerWebhooks()   -> subscribe order webhooks to the webhook service
 */
class ShopifyOAuth
{
    public function isConfigured(): bool
    {
        return ! empty(config('shopify.client_id')) && ! empty(config('shopify.client_secret'));
    }

    /**
     * Normalize user input ("mystore", "mystore.myshopify.com", a URL) into a
     * canonical "<handle>.myshopify.com", or null if it isn't a valid shop.
     */
    public function normalizeDomain(string $input): ?string
    {
        $host = strtolower(trim($input));
        $host = preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0];

        if (! str_contains($host, '.')) {
            $host .= '.myshopify.com';
        }

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $host) ? $host : null;
    }

    /**
     * Build the Shopify authorize URL. The `state` (a CSRF nonce) must be
     * echoed back and verified in the callback.
     */
    public function buildAuthorizeUrl(string $shopDomain, string $state): string
    {
        $query = http_build_query([
            'client_id' => config('shopify.client_id'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => config('shopify.redirect_uri'),
            'state' => $state,
        ]);

        return "https://{$shopDomain}/admin/oauth/authorize?{$query}";
    }

    public function newState(): string
    {
        return Str::random(40);
    }

    /**
     * Verify the HMAC on the OAuth callback query params (Shopify signs all
     * params except `hmac`, sorted, as a query string, HMAC-SHA256 hex).
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyHmac(array $params): bool
    {
        $hmac = $params['hmac'] ?? null;
        if (! $hmac) {
            return false;
        }

        unset($params['hmac'], $params['signature']);
        ksort($params);

        $computed = hash_hmac('sha256', http_build_query($params), config('shopify.client_secret'));

        return hash_equals($computed, (string) $hmac);
    }

    /**
     * Exchange the authorization code for a permanent Admin API access token.
     */
    public function exchangeCodeForToken(string $shopDomain, string $code): string
    {
        $response = Http::asJson()
            ->timeout(config('shopify.timeout'))
            ->post("https://{$shopDomain}/admin/oauth/access_token", [
                'client_id' => config('shopify.client_id'),
                'client_secret' => config('shopify.client_secret'),
                'code' => $code,
            ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Token exchange failed: ' . $response->body());
        }

        return (string) $response->json('access_token');
    }

    /**
     * Subscribe the order webhooks to the inbound-webhook-api service.
     * Best-effort: skipped if no webhook URL is configured.
     */
    public function registerWebhooks(string $shopDomain, string $accessToken): void
    {
        $webhookUrl = config('shopify.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $version = config('shopify.api_version');
        $topics = ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled'];

        foreach ($topics as $topic) {
            Http::withHeaders(['X-Shopify-Access-Token' => $accessToken])
                ->timeout(config('shopify.timeout'))
                ->post("https://{$shopDomain}/admin/api/{$version}/webhooks.json", [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => rtrim($webhookUrl, '/') . '/api/webhooks/shopify',
                        'format' => 'json',
                    ],
                ]);
        }
    }
}
