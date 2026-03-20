<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EventMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
  
    {
    
        return [
            'id' => $this->id,
            'title' => $this->title,
            'caption' => $this->caption,
            'description' => $this->description,
            'altText' => $this->alt_text,
            'mediaType' => $this->media_type,
            'fileUrl' => $this->proxiedUrl($this->file_url),
            'thumbnailUrl' => $this->proxiedUrl($this->thumbnail_url),
            'mimeType' => $this->mime_type,
            'fileSizeBytes' => $this->file_size_bytes,
            'durationSeconds' => $this->duration_seconds,
            'isActive' => (bool) $this->is_active,
            'sortOrder' => $this->sort_order,
            'meta' => $this->meta,
            'uploadedAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function proxiedUrl(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        $path = $value;

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $path = $parsedPath;
            }
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = substr($path, strlen('storage/')) ?: $path;
        }

        if ($path === '') {
            return $value;
        }

        return route('api.v1.media-proxy', ['path' => $path]);
    }
}
