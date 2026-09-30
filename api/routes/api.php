<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopifyConnectController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Shopify OAuth callback (hit by the browser on redirect — no bearer token; the
// request's authenticity is proven by the HMAC + state nonce).
Route::get('/shopify/callback', [ShopifyConnectController::class, 'callback']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Consolidated orders — anyone signed in with the view permission.
    Route::middleware('permission:view orders')->group(function () {
        Route::get('/stats', [StatsController::class, 'index']);
        Route::get('/orders', [SalesOrderController::class, 'index']);
        Route::get('/orders/{order}', [SalesOrderController::class, 'show']);
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/payouts/{payout}', [PayoutController::class, 'show']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/connections', [StoreController::class, 'index']);
    });

    // Managing store connections + triggering syncs — elevated permission.
    Route::middleware('permission:manage connections')->group(function () {
        Route::post('/shopify/connect', [ShopifyConnectController::class, 'connect']);
        Route::post('/connections', [StoreController::class, 'store']);
        Route::put('/connections/{connection}', [StoreController::class, 'update']);
        Route::delete('/connections/{connection}', [StoreController::class, 'destroy']);
        Route::post('/connections/{connection}/sync', [StoreController::class, 'sync']);
    });

    // User management — admin only.
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
