<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'min:2', Rule::unique('products', 'sku')],
            'name' => ['required', 'string', 'min:2'],
            'brand' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'actual_price' => ['required', 'numeric', 'min:0'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'bv' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'weight' => ['required', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', Rule::in(['g', 'kg', 'ml', 'l', 'pcs', 'pack', 'unit', 'box'])],
            'shipping_charge' => ['required', 'numeric', 'min:0'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'categories' => ['array'],
            'categories.*' => ['string'],
            'images' => ['array'],
            'images.*.url' => ['required', 'url'],
            'images.*.alt' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'popularity_score' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_coming_soon' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->input('sku', $this->input('SKU')),
            'name' => $this->input('name', $this->input('productName')),
            'brand' => $this->input('brand', 'Independent'),
            'description' => $this->input('description', $this->input('productDescription')),
            'actual_price' => $this->input('actual_price', $this->input('actualPrice')),
            'total_price' => $this->input('total_price', $this->input('totalPrice')),
            'bv' => $this->input('bv', $this->input('bvValue')),
            'stock' => $this->input('stock', $this->input('inventory')),
            'weight' => $this->input('weight'),
            'weight_unit' => $this->input('weight_unit', $this->input('weightUnit', 'g')),
            'shipping_charge' => $this->input('shipping_charge', $this->input('shippingCharge')),
            'hsn_code' => $this->input('hsn_code', $this->input('hsnCode')),
            'categories' => $this->input('categories', []),
            'images' => $this->input('images', []),
            'rating' => $this->input('rating', 4.5),
            'popularity_score' => $this->input('popularity_score', 0),
            'is_active' => $this->input('is_active', $this->input('isActive', true)),
            'is_coming_soon' => $this->input('is_coming_soon', $this->input('isComingSoon', false)),
            'published_at' => $this->input('published_at', $this->input('publishedAt')),
        ]);
    }
}
