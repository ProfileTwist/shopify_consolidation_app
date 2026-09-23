<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'shopify_id',
        'title',
        'vendor',
        'product_type',
        'status',
        'handle',
        'variants_count',
        'total_inventory',
        'price_min',
        'price_max',
        'image_url',
        'shopify_created_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'variants_count' => 'integer',
            'total_inventory' => 'integer',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'shopify_created_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
