<?php

namespace App\Http\Requests\EventMedia;

use Illuminate\Foundation\Http\FormRequest;

class EventMediaReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer', 'exists:event_media,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
