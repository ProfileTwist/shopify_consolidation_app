<?php

return [

    // "mock" generates sample orders + payouts; "http" hits real stores' Admin API.
    'driver' => env('SHOPIFY_DRIVER', 'mock'),

    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),

    'mock_orders_per_sync' => (int) env('SHOPIFY_MOCK_ORDERS', 25),

    'timeout' => (int) env('SHOPIFY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | OAuth app credentials (for one-click "Connect store")
    |--------------------------------------------------------------------------
    |
    | Set these from your Shopify Partner app. When present, connecting a store
    | runs the real OAuth install; when absent, the connect flow falls back to
    | demo mode (creates a mock store with sample data) so the UX is the same.
    |
    */

    // Same env keys as the Storka monolith so the single LiquorPilot Shopify
    // app's credentials can be reused verbatim.
    'client_id' => env('SHOPIFY_APP_CLIENT_ID'),
    'client_secret' => env('SHOPIFY_APP_CLIENT_SECRET'),

    // Scopes requested during install.
    'scopes' => env('SHOPIFY_SCOPES', 'read_orders,read_all_orders,read_shopify_payments_payouts'),

    // Where Shopify sends the merchant back (this API's callback route).
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI', 'http://localhost:8000/api/shopify/callback'),

    // The SPA to return the merchant to after a successful connect.
    'frontend_url' => env('SHOPIFY_FRONTEND_URL', 'http://localhost:5173'),

    // Base URL of the inbound-webhook-api service (for auto-registering webhooks).
    'webhook_url' => env('SHOPIFY_WEBHOOK_URL'),

    // Whether connecting is allowed to fall back to demo mode when creds are absent.
    'allow_demo' => (bool) env('SHOPIFY_ALLOW_DEMO', true),
];
