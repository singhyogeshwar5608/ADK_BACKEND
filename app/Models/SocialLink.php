<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    use HasFactory;

    protected $table = 'social_links';

    protected $fillable = [
        'platform',
        'url',
        'is_active',
        'title',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
