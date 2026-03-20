<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'caption',
        'description',
        'alt_text',
        'media_type',
        'is_active',
        'file_url',
        'thumbnail_url',
        'mime_type',
        'file_size_bytes',
        'duration_seconds',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];
}
