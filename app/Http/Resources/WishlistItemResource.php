<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'productId' => (string) $this->product_id,
            'addedAt' => $this->created_at?->toIso8601String(),
            'product' => ProductResource::make($this->whenLoaded('product', $this->product)),
        ];
    }
}
