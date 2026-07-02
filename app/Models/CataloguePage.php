<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CataloguePage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image_path',
        'image_public_id',
        'order_index',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];
}
