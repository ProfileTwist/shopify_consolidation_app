<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shopify_id' => $this->shopify_id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'financial_status' => $this->financial_status,
            'fulfillment_status' => $this->fulfillment_status,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'account_name' => $this->account_name,
            'customer_email' => $this->customer_email,
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'shopify_created_at' => $this->shopify_created_at,
            'shopify_updated_at' => $this->shopify_updated_at,
            'connection' => [
                'id' => $this->store_id,
                'name' => $this->whenLoaded('connection', fn () => $this->connection->name),
            ],
            'line_items' => SalesOrderLineItemResource::collection($this->whenLoaded('lineItems')),
        ];
    }
}
