<?php

namespace App\Http\Requests\CataloguePage;

use Illuminate\Foundation\Http\FormRequest;

class StoreCataloguePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'image' => ['required', 'image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
