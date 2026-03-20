<?php

namespace App\Http\Requests\EventMedia;

use Illuminate\Foundation\Http\FormRequest;

class EventMediaBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:activate,deactivate,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:event_media,id'],
        ];
    }
}
