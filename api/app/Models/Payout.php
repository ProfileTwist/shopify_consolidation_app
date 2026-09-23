<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    protected $fillable = [
        'store_id',
        'external_id',
        'status',
        'issued_at',
        'currency',
        'amount',
        'gross',
        'fees',
        'refunds',
        'adjustments',
        'reserved',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'amount' => 'decimal:2',
            'gross' => 'decimal:2',
            'fees' => 'decimal:2',
            'refunds' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'reserved' => 'decimal:2',
            'raw' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }
}
