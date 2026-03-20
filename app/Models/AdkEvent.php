<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdkEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'leader_name',
        'meeting_date',
        'meeting_time',
        'store_name',
        'address',
        'state',
        'city',
        'leader_mobile',
        'store_mobile',
        'notes',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];
}
