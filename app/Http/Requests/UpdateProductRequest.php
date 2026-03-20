<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => [
                'sometimes',
                'string',
                'min:2',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => ['sometimes', 'string', 'min:2'],
            'brand' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'actual_price' => ['sometimes', 'numeric', 'min:0'],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'bv' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string'],
            'images' => ['sometimes', 'array'],
            'images.*.url' => ['required_with:images', 'url'],
            'images.*.alt' => ['nullable', 'string'],
            'rating' => ['sometimes', 'numeric', 'between:0,5'],
            'popularity_score' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->input('sku', $this->input('SKU', $this->input('productSku'))),
            'name' => $this->input('name', $this->input('productName')),
            'description' => $this->input('description', $this->input('productDescription')),
            'actual_price' => $this->input('actual_price', $this->input('actualPrice')),
            'total_price' => $this->input('total_price', $this->input('totalPrice')),
            'bv' => $this->input('bv', $this->input('bvValue')),
            'stock' => $this->input('stock', $this->input('inventory')),
            'published_at' => $this->input('published_at', $this->input('publishedAt')),
        ]);
    }
}
