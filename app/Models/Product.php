<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'brand',
        'description',
        'actual_price',
        'total_price',
        'bv',
        'stock',
        'categories',
        'images',
        'rating',
        'popularity_score',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'actual_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'bv' => 'decimal:2',
        'stock' => 'integer',
        'categories' => 'array',
        'images' => 'array',
        'rating' => 'float',
        'popularity_score' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected function getImagesAttribute($value): array
    {
        $images = $value;

        if (is_string($images)) {
            $decoded = json_decode($images, true, 512, JSON_THROW_ON_ERROR);
            $images = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($images)) {
            return [];
        }

        return collect($images)
            ->filter(fn ($image) => is_array($image))
            ->map(function (array $image) {
                return [
                    'url' => $this->proxyUrl($image['url'] ?? ''),
                    'alt' => $image['alt'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function proxyUrl(?string $url): string
    {
        $normalized = $url ? trim($url) : '';

        if ($normalized === '') {
            return '';
        }

        if (Str::startsWith($normalized, ['http://', 'https://'])) {
            $path = parse_url($normalized, PHP_URL_PATH) ?? '';
            if ($path && Str::startsWith($path, '/storage/')) {
                return route('storage.proxy', ['path' => ltrim($path, '/')]);
            }

            return $normalized;
        }

        $relativePath = ltrim($normalized, '/');

        if (Str::startsWith($relativePath, 'storage/')) {
            return route('storage.proxy', ['path' => $relativePath]);
        }

        return asset($relativePath);
    }
}
