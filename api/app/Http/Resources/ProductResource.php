<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shopify_id' => $this->shopify_id,
            'title' => $this->title,
            'vendor' => $this->vendor,
            'product_type' => $this->product_type,
            'status' => $this->status,
            'variants_count' => $this->variants_count,
            'total_inventory' => $this->total_inventory,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'image_url' => $this->image_url,
            'store' => [
                'id' => $this->store_id,
                'name' => $this->whenLoaded('store', fn () => $this->store->name),
            ],
        ];
    }
}
