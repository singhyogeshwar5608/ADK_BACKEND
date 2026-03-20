<?php

namespace App\Http\Requests\EventMedia;

use Illuminate\Foundation\Http\FormRequest;

class EventMediaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'media_type' => $this->input('mediaType', $this->input('media_type')),
            'file_url' => $this->input('fileUrl', $this->input('file_url')),
            'thumbnail_url' => $this->input('thumbnailUrl', $this->input('thumbnail_url')),
            'alt_text' => $this->input('altText', $this->input('alt_text')),
            'is_active' => $this->input('isActive', $this->input('status')),
            'file_size_bytes' => $this->input('fileSize', $this->input('file_size_bytes')),
            'duration_seconds' => $this->input('duration', $this->input('duration_seconds')),
            'sort_order' => $this->input('sortOrder', $this->input('sort_order')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'alt_text' => ['nullable', 'string', 'max:180'],
            'media_type' => ['sometimes', 'in:IMAGE,VIDEO,image,video'],
            'file_url' => ['sometimes', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'file_size_bytes' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if (isset($data['media_type'])) {
            $data['media_type'] = strtoupper($data['media_type']);
        }

        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        return $data;
    }
}
