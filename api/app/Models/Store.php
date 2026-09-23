<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'name',
        'platform',
        'instance_url',
        'shop_domain',
        'auth_type',
        'client_id',
        'client_secret',
        'username',
        'password',
        'access_token',
        'token_expires_at',
        'is_active',
        'sync_interval_seconds',
        'status',
        'payments_enabled',
        'last_synced_at',
        'last_error',
    ];

    /**
     * Credentials are encrypted at rest and never serialized to API responses.
     */
    protected $hidden = [
        'client_id',
        'client_secret',
        'username',
        'password',
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'payments_enabled' => 'boolean',
            'sync_interval_seconds' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
