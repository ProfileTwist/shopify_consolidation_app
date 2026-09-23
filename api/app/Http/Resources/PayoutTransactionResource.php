<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net' => $this->net,
            'source_order_external_id' => $this->source_order_external_id,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ] : null),
        ];
    }
}
