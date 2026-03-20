<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'member_id',
        'sponsor_id',
        'leg',
        'placement_path',
        'depth',
        'full_name',
        'email',
        'phone',
        'profile_image',
        'role',
        'password_hash',
        'status',
        'wallet_balance',
        'wallet_total_earned',
        'bv_total',
        'bv_left_leg',
        'bv_right_leg',
        'bv_carry_forward_left',
        'bv_carry_forward_right',
        'stats_team_size',
        'stats_direct_refs',
        'last_login_at',
    ];

    protected $casts = [
        'leg' => 'string',
        'placement_path' => 'string',
        'depth' => 'integer',
        'wallet_balance' => 'decimal:2',
        'wallet_total_earned' => 'decimal:2',
        'bv_total' => 'decimal:2',
        'bv_left_leg' => 'decimal:2',
        'bv_right_leg' => 'decimal:2',
        'bv_carry_forward_left' => 'decimal:2',
        'bv_carry_forward_right' => 'decimal:2',
        'stats_team_size' => 'integer',
        'stats_direct_refs' => 'integer',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function sponsor()
    {
        return $this->belongsTo(self::class, 'sponsor_id');
    }

    public function downline()
    {
        return $this->hasMany(self::class, 'sponsor_id');
    }

    public function leftChild(): HasOne
    {
        return $this->hasOne(self::class, 'sponsor_id')->where('leg', 'LEFT')->orderBy('id');
    }

    public function rightChild(): HasOne
    {
        return $this->hasOne(self::class, 'sponsor_id')->where('leg', 'RIGHT')->orderBy('id');
    }
}
