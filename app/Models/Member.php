<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'address',
        'city',
        'state',
        'profile_image',
        'qr_code_url',
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
        'weekly_income',
        'weekly_income_reset_date',
        'is_active',
        'minimum_bv_required',
        'direct_referrals_count',
        'is_repurchase_eligible',
        'first_purchase_at',
        'total_matched_bv',
        'first_match_done',
        'reward_eligible',
        'level_completion',
        // KYC Document Fields
        'bank_account_number',
        'bank_account_image',
        'aadhar_number',
        'aadhar_image',
        'pan_number',
        'pan_image',
        'kyc_status',
        'kyc_rejection_reason',
        'kyc_verified_at',
        'serial_no',
        'type',
        'referred_by',
    ];

    protected static function booted()
    {
        static::creating(function ($member) {
            if ($member->serial_no === null) {
                $member->serial_no = (static::max('serial_no') ?? 0) + 1;
            }
        });
    }

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
        'weekly_income' => 'decimal:2',
        'weekly_income_reset_date' => 'date',
        'is_active' => 'boolean',
        'minimum_bv_required' => 'decimal:2',
        'direct_referrals_count' => 'integer',
        'is_repurchase_eligible' => 'boolean',
        'first_purchase_at' => 'datetime',
        'total_matched_bv' => 'decimal:2',
        'first_match_done' => 'boolean',
        'reward_eligible' => 'boolean',
        'level_completion' => 'integer',
        // KYC Document Fields
        'kyc_status' => 'string',
        'kyc_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function toArray()
    {
        $array = parent::toArray();
        // Add camelCase aliases for frontend
        if (isset($array['member_id'])) {
            $array['memberId'] = $array['member_id'];
        }
        if (isset($array['full_name'])) {
            $array['fullName'] = $array['full_name'];
        }
        if (isset($array['profile_image'])) {
            $array['profileImage'] = $array['profile_image'];
        }
        if (isset($array['qr_code_url'])) {
            $array['qrCodeUrl'] = $array['qr_code_url'];
        }
        return $array;
    }

    /**
     * Admin-panel modules shared with staff (products, categories, catalogue, media, etc.).
     */
    public function canAccessStaffPanelModules(): bool
    {
        return in_array($this->role, ['ADMIN', 'MEMBER'], true);
    }

    public function sponsor(): BelongsTo
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

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * True when at least one order has completed payment (used for binary tree card: green vs yellow).
     */
    public function hasPaidProductPurchase(): bool
    {
        return Order::query()
            ->where('member_id', $this->id)
            ->where('payment_status', 'PAID')
            ->exists();
    }

    public function incomeTransactions()
    {
        return $this->hasMany(IncomeTransaction::class);
    }

    public function matchingHistory()
    {
        return $this->hasMany(MatchingHistory::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function incomeLedger(): HasMany
    {
        return $this->hasMany(IncomeLedger::class);
    }

    public function bvLedger(): HasMany
    {
        return $this->hasMany(BvLedger::class);
    }

    /**
     * Account is allowed to use member-app features that require an active standing (e.g. binary tree).
     * Admin panel uses `status` (ACTIVE / SUSPENDED / PENDING).
     */
    public function hasActiveAccountStanding(): bool
    {
        return strtoupper(trim((string) $this->status)) === 'ACTIVE';
    }

    public function calculateTeamSize(): int
    {
        return Member::where(function ($q) {
                $q->where('referred_by', $this->member_id)
                  ->orWhere('sponsor_id', $this->id);
            })
            ->where('id', '!=', $this->id)
            ->count();
    }

    public function calculateActiveTeamSize(): int
    {
        return Member::where(function ($q) {
                $q->where('referred_by', $this->member_id)
                  ->orWhere('sponsor_id', $this->id);
            })
            ->where('id', '!=', $this->id)
            ->where('status', 'ACTIVE')
            ->count();
    }

    public function calculateInactiveTeamSize(): int
    {
        return Member::where(function ($q) {
                $q->where('referred_by', $this->member_id)
                  ->orWhere('sponsor_id', $this->id);
            })
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'ACTIVE')
            ->count();
    }

    public function calculateTotalTeamBV(): float
    {
        if (!$this->placement_path) {
            return 0;
        }
        
        // Sum BV of all members in the downline (excluding self)
        return Member::where('placement_path', 'like', $this->placement_path . '.%')
            ->where('id', '!=', $this->id)
            ->sum('bv_total') ?? 0;
    }
}
