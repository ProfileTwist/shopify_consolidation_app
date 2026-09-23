<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Per-connection job runner: ticks every 10s and dispatches each active
// connection's sync when its own interval has elapsed (sub-minute scheduling
// requires `php artisan schedule:work`).
Schedule::command('sync:due')
    ->everyTenSeconds()
    ->withoutOverlapping();

// Reconciliation safety-net: re-pull every active connection hourly so any
// missed webhook/event self-heals. Runs across all stores; each job is isolated.
Schedule::command('shopify:sync')
    ->hourly()
    ->withoutOverlapping();
