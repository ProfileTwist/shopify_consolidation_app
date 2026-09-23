<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'store_id',
        'shopify_id',
        'order_number',
        'status',
        'financial_status',
        'fulfillment_status',
        'cancelled_at',
        'cancel_reason',
        'account_name',
        'customer_email',
        'currency',
        'total_amount',
        'shopify_created_at',
        'shopify_updated_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'shopify_created_at' => 'datetime',
            'shopify_updated_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(SalesOrderLineItem::class);
    }
}
