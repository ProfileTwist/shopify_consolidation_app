<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toDateString(),
            'currency' => $this->currency,
            'amount' => $this->amount,
            'gross' => $this->gross,
            'fees' => $this->fees,
            'refunds' => $this->refunds,
            'adjustments' => $this->adjustments,
            'reserved' => $this->reserved,
            'connection' => [
                'id' => $this->store_id,
                'name' => $this->whenLoaded('connection', fn () => $this->connection->name),
            ],
            'transactions_count' => $this->whenCounted('transactions'),
            'transactions' => PayoutTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
