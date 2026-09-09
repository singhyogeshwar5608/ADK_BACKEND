<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyIncomeSnapshot extends Model
{
    protected $fillable = [
        'member_id',
        'year_month',
        'direct',
        'matching',
        'self_purchase',
        'self_repurchase',
        'sponsor',
        'sponsor_award_kit',
        'repurchase_matching',
        'downline_monthly_sponsor',
        'total',
        'is_paid',
        'paid_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'direct' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'matching' => 'decimal:2',
        'self_purchase' => 'decimal:2',
        'self_repurchase' => 'decimal:2',
        'sponsor' => 'decimal:2',
        'sponsor_award_kit' => 'decimal:2',
        'repurchase_matching' => 'decimal:2',
        'downline_monthly_sponsor' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
