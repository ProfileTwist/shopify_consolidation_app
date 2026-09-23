<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'shop_domain' => $this->shop_domain,
            'instance_url' => $this->instance_url,
            'auth_type' => $this->auth_type,
            'is_active' => $this->is_active,
            'sync_interval_seconds' => $this->sync_interval_seconds,
            'status' => $this->status,
            'payments_enabled' => $this->payments_enabled,
            'last_synced_at' => $this->last_synced_at,
            'last_error' => $this->last_error,
            'orders_count' => $this->whenCounted('orders'),
            'payouts_count' => $this->whenCounted('payouts'),
            'created_at' => $this->created_at,
        ];
    }
}
