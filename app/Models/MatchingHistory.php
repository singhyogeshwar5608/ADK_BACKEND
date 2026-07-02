<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchingHistory extends Model
{
    use HasFactory;

    protected $table = 'matching_history';

    protected $fillable = [
        'member_id',
        'left_bv_matched',
        'right_bv_matched',
        'matched_bv',
        'income_earned',
        'percentage_applied',
        'is_first_match',
        'match_ratio',
    ];

    protected $casts = [
        'left_bv_matched' => 'decimal:2',
        'right_bv_matched' => 'decimal:2',
        'matched_bv' => 'decimal:2',
        'income_earned' => 'decimal:2',
        'percentage_applied' => 'decimal:2',
        'is_first_match' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
