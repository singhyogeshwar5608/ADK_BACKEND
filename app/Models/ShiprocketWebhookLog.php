<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiprocketWebhookLog extends Model
{
    protected $fillable = [
        'order_id',
        'awb',
        'event',
        'payload',
    ];

    protected $casts = [
        'payload' => 'json',
    ];
}
