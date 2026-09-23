<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $fillable = [
        'store_id',
        'status',
        'orders_synced',
        'started_at',
        'finished_at',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'orders_synced' => 'integer',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
