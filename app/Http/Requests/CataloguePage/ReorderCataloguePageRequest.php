<?php

namespace App\Http\Requests\CataloguePage;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCataloguePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer', 'exists:catalogue_pages,id'],
            'order.*.order_index' => ['required', 'integer', 'min:1'],
        ];
    }
}
