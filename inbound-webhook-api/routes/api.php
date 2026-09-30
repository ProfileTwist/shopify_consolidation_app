<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Inbound webhooks: POST /api/webhooks/{source}  (e.g. /api/webhooks/shopify)
// No auth middleware — authenticity is proven by the per-source HMAC signature.
Route::post('/webhooks/{source}', WebhookController::class);
