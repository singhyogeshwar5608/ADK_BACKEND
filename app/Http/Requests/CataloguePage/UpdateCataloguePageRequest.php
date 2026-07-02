<?php

namespace App\Http\Requests\CataloguePage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCataloguePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessStaffPanelModules();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'image' => ['sometimes', 'image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
