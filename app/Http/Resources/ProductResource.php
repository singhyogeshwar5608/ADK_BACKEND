<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'description' => $this->description,
            'actualPrice' => (float) $this->actual_price,
            'totalPrice' => (float) $this->total_price,
            'discountPercentage' => (float) ($this->discount_percentage ?? 0),
            'bv' => (float) $this->bv,
            'stock' => (int) $this->stock,
            'weight' => (float) ($this->weight ?? 0),
            'weightUnit' => $this->weight_unit ?? 'g',
            'shippingCharge' => (float) ($this->shipping_charge ?? 0),
            'gstPercent' => (float) ($this->gst_percent ?? 0),
            'rating' => (float) ($this->rating ?? 0),
            'popularityScore' => (int) ($this->popularity_score ?? 0),
            'isActive' => (bool) $this->is_active,
            'isComingSoon' => (bool) $this->is_coming_soon,
            'is_coming_soon' => (bool) $this->is_coming_soon, // Fallback for various mappings
            'publishedAt' => $this->published_at?->toIso8601String(),
            'categories' => $this->categories ?? [],
            'images' => $this->images,
            'primaryImage' => $this->images[0]['url'] ?? null,
        ];
    }
}
