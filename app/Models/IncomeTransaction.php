<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'type',
        'amount',
        'bv',
        'source_type',
        'source_id',
        'from_member_id',
        'description',
        'meta',
        'is_capped',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bv' => 'decimal:2',
        'meta' => 'array',
        'is_capped' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fromMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'from_member_id');
    }

    public function source()
    {
        return $this->morphTo();
    }
}
