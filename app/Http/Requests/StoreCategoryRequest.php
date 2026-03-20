<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2'],
            'slug' => ['nullable', 'string', Rule::unique('categories', 'slug')],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $slug = $this->input('slug');

        $this->merge([
            'slug' => $slug ? Str::slug($slug) : ($name ? Str::slug($name) : null),
            'is_active' => $this->input('is_active', true),
        ]);
    }
}
