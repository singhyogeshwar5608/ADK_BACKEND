<?php

namespace App\Http\Requests\EventMedia;

use Illuminate\Foundation\Http\FormRequest;

class EventMediaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'media_type' => strtoupper((string) $this->input('mediaType', $this->input('media_type'))),
            'file_url' => $this->input('fileUrl', $this->input('file_url')),
            'thumbnail_url' => $this->input('thumbnailUrl', $this->input('thumbnail_url')),
            'alt_text' => $this->input('altText', $this->input('alt_text')),
            'is_active' => $this->input('isActive', $this->input('status', true)),
            'file_size_bytes' => $this->input('fileSize', $this->input('file_size_bytes')),
            'duration_seconds' => $this->input('duration', $this->input('duration_seconds')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'alt_text' => ['nullable', 'string', 'max:180'],
            'media_type' => ['required', 'in:IMAGE,VIDEO'],
            'file_url' => ['required', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'file_size_bytes' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        return $data;
    }
}
