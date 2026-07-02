<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CataloguePageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'imageUrl' => $this->proxiedUrl($this->image_path),
            'imagePath' => $this->image_path,
            'orderIndex' => $this->order_index,
            'isActive' => (bool) $this->is_active,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function proxiedUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $path = $value;

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $host = parse_url($path, PHP_URL_HOST) ?: '';
            $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: '';

            if ($host && $appHost && ! Str::contains($host, $appHost)) {
                return $value; // External asset (e.g. Cloudinary) should not be proxied
            }

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
