<?php

namespace App\Services;

use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\Order;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLMIncomeService
{
    private int $weeklyCap;
    private float $selfIncomePercent;
    private float $sponsorIncomePercent;
    private float $matchingIncomePercent;
    private float $rewardIncomePercent;
    private float $repurchaseSelfPercent;
    private float $repurchaseMatchingPercent;
    private float $repurchaseRewardPercent;
    private float $sponsorAwardPercent;
    
    // Matching ratio
    private const MATCH_RATIO = '1:1';
    
    // Reward eligibility
    private const REWARD_DIRECT_REFERRALS_REQUIRED = 25;
    
    // Minimum BV threshold for sponsor income / award kit income
    private const KIT_BV_THRESHOLD = 5000;

    public function __construct(
        private readonly MatchingService $matchingService,
        private readonly MlmSettingsService $settingsService
    ) {
        $this->loadIncomeSettings();
    }

    /**
     * Load income percentages from database settings
     */
    private function loadIncomeSettings(): void
    {
        $this->weeklyCap = (int) $this->settingsService->getSetting('weekly_capping', 50000);
        $this->selfIncomePercent = (float) $this->settingsService->getSetting('self_income_percent', 10) / 100;
        $this->sponsorIncomePercent = (float) $this->settingsService->getSetting('direct_income_percent', 20) / 100;
        $this->matchingIncomePercent = (float) $this->settingsService->getSetting('matching_income_percent', 10) / 100;
        $this->rewardIncomePercent = (float) $this->settingsService->getSetting('award_income_percent', 20) / 100;
        $this->repurchaseSelfPercent = (float) $this->settingsService->getSetting('self_repurchase_income_percent', 10) / 100;
        $this->repurchaseMatchingPercent = (float) $this->settingsService->getSetting('repurchase_matching_income_percent', 10) / 100;
        $this->repurchaseRewardPercent = (float) $this->settingsService->getSetting('award_income_percent', 20) / 100; // Same as reward
        $this->sponsorAwardPercent = (float) $this->settingsService->getSetting('award_income_percent', 20) / 100; // Same as reward percentage
    }

    /**
     * Process complete income distribution for a purchase
     */
    public function processPurchaseIncome(Member $purchaser, float $bv, $sourceType, $sourceId, bool $isRepurchase = false): array
    {
        $results = [];
        
        DB::transaction(function () use ($purchaser, $bv, $sourceType, $sourceId, $isRepurchase, &$results) {
            // 1. Self Income
            $selfIncome = $this->distributeSelfIncome($purchaser, $bv, $sourceType, $sourceId, $isRepurchase);
            $results['self_income'] = $selfIncome;
            
            // 2. Sponsor Income (first purchase, any BV) / Sponsor Award (repurchase >= 5000BV)
            if ($purchaser->sponsor_id) {
                $sponsor = $this->resolveSponsor($purchaser);
                if ($sponsor) {
                    $hasPreviousSponsorIncome = IncomeTransaction::where('type', 'SPONSOR')
                        ->where('member_id', $sponsor->id)
                        ->where('from_member_id', $purchaser->id)
                        ->exists();
                    
                    Log::info('MLMIncomeService: Sponsor income check', [
                        'purchaser_id' => $purchaser->id,
                        'purchaser_member_id' => $purchaser->member_id,
                        'sponsor_id' => $sponsor->id,
                        'sponsor_member_id' => $sponsor->member_id,
                        'bv' => $bv,
                        'has_previous_sponsor_income' => $hasPreviousSponsorIncome,
                        'is_repurchase' => $isRepurchase,
                    ]);
                    
                    if (!$hasPreviousSponsorIncome) {
                        // First purchase (any BV) → sponsor income
                        $sponsorIncome = $this->distributeSponsorIncome($purchaser, $bv, $sourceType, $sourceId);
                        $results['sponsor_income'] = $sponsorIncome;
                    } elseif ($bv >= self::KIT_BV_THRESHOLD) {
                        // Repurchase ≥ 5000 BV → sponsor award
                        $sponsorAward = $this->distributeSponsorAward($purchaser, $bv, $sourceType, $sourceId);
                        $results['sponsor_award'] = $sponsorAward;
                    }
                } else {
                    Log::warning('MLMIncomeService: Sponsor not found for purchase', [
                        'purchaser_id' => $purchaser->id,
                        'purchaser_member_id' => $purchaser->member_id,
                        'sponsor_id_field' => $purchaser->sponsor_id,
                        'referred_by' => $purchaser->referred_by,
                    ]);
                }
            }
            
            // 3. Propagate BV up the tree
            $this->propagateBVUpTree($purchaser, $bv, $sourceType, $sourceId);
            
            // 4. Trigger matching income for all uplines
            $matchingResults = $this->triggerMatchingIncomeForUplines($purchaser, $isRepurchase);
            $results['matching_income'] = $matchingResults;
            
            // 5. Reward Income (if eligible)
            $rewardIncome = $this->distributeRewardIncome($purchaser, $bv, $sourceType, $sourceId, $isRepurchase);
            $results['reward_income'] = $rewardIncome;
            
            // 6. Update member activity status
            $isOrder = $sourceType === 'ORDER';
            $this->updateMemberActivity($purchaser, $bv, $isOrder ? $sourceId : null);
        });
        
        return $results;
    }

    /**
     * Distribute self income to purchaser
     */
    private function distributeSelfIncome(Member $purchaser, float $bv, $sourceType, $sourceId, bool $isRepurchase): array
    {
        $percentage = $isRepurchase ? $this->repurchaseSelfPercent : $this->selfIncomePercent;
        $amount = $bv * $percentage;
        $type = $isRepurchase ? 'REPURCHASE_SELF' : 'SELF';
        
        $cappedAmount = $this->applyWeeklyCap($purchaser, $amount);
        
        if ($cappedAmount > 0) {
            $this->creditWallet($purchaser, $cappedAmount, $type, $bv, $sourceType, $sourceId, null, [
                'percentage' => $percentage * 100,
                'original_amount' => $amount,
                'capped' => $amount !== $cappedAmount,
            ]);
        }
        
        return [
            'member_id' => $purchaser->id,
            'amount' => $cappedAmount,
            'original_amount' => $amount,
            'capped' => $amount !== $cappedAmount,
        ];
    }

    /**
     * Resolve the actual sponsor for income distribution.
     * Uses referred_by first (referral link owner), then falls back to sponsor_id (binary tree parent).
     */
    private function resolveSponsor(Member $purchaser): ?Member
    {
        if ($purchaser->referred_by) {
            $sponsor = Member::where('member_id', $purchaser->referred_by)->first();
            if ($sponsor) {
                return $sponsor;
            }
        }
        
        if ($purchaser->sponsor_id) {
            return Member::find($purchaser->sponsor_id);
        }
        
        return null;
    }

    /**
     * Distribute sponsor income to direct sponsor
     */
    private function distributeSponsorIncome(Member $purchaser, float $bv, $sourceType, $sourceId): ?array
    {
        $sponsor = $this->resolveSponsor($purchaser);
        
        if (!$sponsor) {
            Log::warning('MLMIncomeService: No referrer/sponsor found for purchaser', [
                'purchaser_id' => $purchaser->id,
                'purchaser_member_id' => $purchaser->member_id,
                'referred_by' => $purchaser->referred_by,
                'sponsor_id' => $purchaser->sponsor_id,
            ]);
            return null;
        }
        
        $amount = $bv * $this->sponsorIncomePercent;
        $cappedAmount = $this->applyWeeklyCap($sponsor, $amount);

        Log::info('MLMIncomeService: Sponsor income', [
            'sponsor_id' => $sponsor->id,
            'sponsor_member_id' => $sponsor->member_id,
            'purchaser_member_id' => $purchaser->member_id,
            'bv' => $bv,
            'percentage' => $this->sponsorIncomePercent * 100,
            'amount' => $amount,
            'capped_amount' => $cappedAmount,
        ]);
        
        if ($cappedAmount > 0) {
            $this->creditWallet($sponsor, $cappedAmount, 'SPONSOR', $bv, $sourceType, $sourceId, $purchaser->id, [
                'percentage' => $this->sponsorIncomePercent * 100,
                'original_amount' => $amount,
                'capped' => $amount !== $cappedAmount,
                'from_member' => $purchaser->member_id,
            ]);
        }
        
        return [
            'member_id' => $sponsor->id,
            'amount' => $cappedAmount,
            'original_amount' => $amount,
            'capped' => $amount !== $cappedAmount,
        ];
    }

    /**
     * Propagate BV up the binary tree
     */
    private function propagateBVUpTree(Member $purchaser, float $bv, $sourceType, $sourceId): void
    {
        $currentPath = $purchaser->placement_path;
        $segments = explode('.', $currentPath);
        
        // Remove the last segment (purchaser's own position)
        array_pop($segments);
        
        while (!empty($segments)) {
            $parentPath = implode('.', $segments);
            $parent = Member::where('placement_path', $parentPath)->lockForUpdate()->first();
            
            if (!$parent) {
                break;
            }
            
            // Determine if BV goes to left or right leg
            $direction = $this->determineDirection($parentPath, $currentPath);
            
            // Update parent's BV
            $parent->bv_total += $bv;
            
            if ($direction === 'LEFT') {
                $parent->bv_left_leg += $bv;
                $parent->bv_carry_forward_left += $bv;
            } else {
                $parent->bv_right_leg += $bv;
                $parent->bv_carry_forward_right += $bv;
            }
            
            $parent->save();
            
            // Move up the tree
            array_pop($segments);
        }
    }

    /**
     * Trigger matching income calculation for all uplines
     */
    private function triggerMatchingIncomeForUplines(Member $purchaser, bool $isRepurchase): array
    {
        $results = [];
        $currentPath = $purchaser->placement_path;
        $segments = explode('.', $currentPath);
        
        // Remove the last segment
        array_pop($segments);
        
        while (!empty($segments)) {
            $parentPath = implode('.', $segments);
            $parent = Member::where('placement_path', $parentPath)->lockForUpdate()->first();
            
            if (!$parent) {
                break;
            }
            
            // Calculate and distribute matching income
            $matchingResult = $this->matchingService->calculateAndDistributeMatching($parent, $isRepurchase);
            
            if ($matchingResult['income_earned'] > 0) {
                $results[] = $matchingResult;
            }
            
            // Move up the tree
            array_pop($segments);
        }
        
        return $results;
    }

    /**
     * Distribute reward income (6% or 10% for repurchase)
     */
    private function distributeRewardIncome(Member $purchaser, float $bv, $sourceType, $sourceId, bool $isRepurchase): ?array
    {
        // Calculate real-time count of active direct referrals who have purchased
        $activeDirectReferrals = Member::where(function ($q) use ($purchaser) {
                $q->where('referred_by', $purchaser->member_id)
                  ->orWhere('sponsor_id', $purchaser->id);
            })
            ->where('status', 'ACTIVE')
            ->whereNotNull('first_purchase_at')
            ->count();

        // Check if member is eligible for reward income
        if (!$purchaser->reward_eligible && $activeDirectReferrals < self::REWARD_DIRECT_REFERRALS_REQUIRED) {
            return null;
        }
        
        $percentage = $isRepurchase ? $this->repurchaseRewardPercent : $this->rewardIncomePercent;
        $amount = $bv * $percentage;
        $type = $isRepurchase ? 'REPURCHASE_REWARD' : 'REWARD';
        
        $cappedAmount = $this->applyWeeklyCap($purchaser, $amount);
        
        if ($cappedAmount > 0) {
            $this->creditWallet($purchaser, $cappedAmount, $type, $bv, $sourceType, $sourceId, null, [
                'percentage' => $percentage * 100,
                'original_amount' => $amount,
                'capped' => $amount !== $cappedAmount,
                'direct_referrals' => $activeDirectReferrals,
            ]);
        }
        
        return [
            'member_id' => $purchaser->id,
            'amount' => $cappedAmount,
            'original_amount' => $amount,
            'capped' => $amount !== $cappedAmount,
        ];
    }

    /**
     * Apply weekly income cap
     */
    private function applyWeeklyCap(Member $member, float $amount): float
    {
        // Reset weekly income if needed
        $this->resetWeeklyIncomeIfNeeded($member);
        
        $currentWeeklyIncome = (float) $member->weekly_income;
        $remainingCap = $this->weeklyCap - $currentWeeklyIncome;
        
        if ($remainingCap <= 0) {
            return 0;
        }
        
        return min($amount, $remainingCap);
    }

    /**
     * Reset weekly income if a week has passed
     */
    private function resetWeeklyIncomeIfNeeded(Member $member): void
    {
        if (!$member->weekly_income_reset_date || 
            Carbon::parse($member->weekly_income_reset_date)->addWeek()->isPast()) {
            
            $member->weekly_income = 0;
            $member->weekly_income_reset_date = Carbon::now()->toDateString();
            $member->save();
        }
    }

    /**
     * Credit wallet and create transaction records
     */
    private function creditWallet(
        Member $member,
        float $amount,
        string $type,
        float $bv,
        $sourceType,
        $sourceId,
        ?int $fromMemberId,
        array $meta = []
    ): void {
        // Update member wallet
        $member->wallet_balance += $amount;
        $member->wallet_total_earned += $amount;
        $member->weekly_income += $amount;
        $member->save();
        
        // Create income transaction
        try {
            IncomeTransaction::create([
                'member_id' => $member->id,
                'type' => $type,
                'amount' => $amount,
                'bv' => $bv,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'from_member_id' => $fromMemberId,
                'description' => $this->getIncomeDescription($type, $meta),
                'meta' => $meta,
                'is_capped' => $meta['capped'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MLMIncomeService: Failed to create income transaction, continuing...', [
                'type' => $type,
                'amount' => $amount,
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Create wallet transaction
        WalletTransaction::create([
            'member_id' => $member->id,
            'type' => 'CREDIT',
            'amount' => $amount,
            'balance_after' => $member->wallet_balance,
            'reference' => strtoupper(substr($type, 0, 3)) . '-' . now()->timestamp . '-' . $member->id,
            'context' => $type . '_INCOME',
            'meta' => $meta,
        ]);
    }

    /**
     * Update member activity status
     */
    private function updateMemberActivity(Member $member, float $bv, ?int $currentOrderId = null): void
    {
        $isFirstPurchase = !$member->first_purchase_at;

        if (!$member->first_purchase_at) {
            $member->first_purchase_at = now();
        }

        $referrerMethod = $member->referred_by
            ? ['field' => 'member_id', 'value' => $member->referred_by]
            : ($member->sponsor_id ? ['field' => 'id', 'value' => $member->sponsor_id] : null);

        // Family Tour Award: count when ANY purchase ≥ 5000 BV
        // (first time or repurchase, as long as this member hasn't been counted before)
        if ($referrerMethod && $bv >= self::KIT_BV_THRESHOLD && $member->status === 'ACTIVE') {
            $alreadyHasHighBvOrder = Order::where('member_id', $member->id)
                ->where('total_bv', '>=', self::KIT_BV_THRESHOLD)
                ->when($currentOrderId, fn ($q) => $q->where('id', '!=', $currentOrderId))
                ->exists();

            if (!$alreadyHasHighBvOrder) {
                Member::query()
                    ->where($referrerMethod['field'], $referrerMethod['value'])
                    ->increment('stats_direct_refs');
            }
        }

        // On first purchase, increment reward eligibility count regardless of BV
        if ($isFirstPurchase && $member->status === 'ACTIVE' && $referrerMethod) {
            Member::query()
                ->where($referrerMethod['field'], $referrerMethod['value'])
                ->increment('direct_referrals_count');
        }
        
        // Mark as active if BV meets minimum requirement
        if ($bv >= $member->minimum_bv_required) {
            $member->is_active = true;
        }
        
        // Check repurchase eligibility
        if ($member->first_purchase_at && $member->first_purchase_at->diffInDays(now()) > 0) {
            $member->is_repurchase_eligible = true;
        }
        
        // Recalculate reward eligibility from DB (count only ACTIVE members with purchases)
        $activeDirectReferrals = Member::where(function ($q) use ($member) {
                $q->where('referred_by', $member->member_id)
                  ->orWhere('sponsor_id', $member->id);
            })
            ->where('status', 'ACTIVE')
            ->whereNotNull('first_purchase_at')
            ->count();

        $member->direct_referrals_count = $activeDirectReferrals;
        $member->reward_eligible = $activeDirectReferrals >= self::REWARD_DIRECT_REFERRALS_REQUIRED;
        
        $member->save();
    }

    /**
     * Determine direction (LEFT or RIGHT) from parent to child
     */
    private function determineDirection(string $parentPath, string $childPath): string
    {
        if (!str_starts_with($childPath, $parentPath)) {
            return 'RIGHT';
        }
        
        $offset = substr($childPath, strlen($parentPath));
        $offset = ltrim($offset, '.');
        $first = strtoupper(substr($offset, 0, 1));
        
        return $first === 'L' ? 'LEFT' : 'RIGHT';
    }

    /**
     * Distribute Sponsor Award for repurchases (5000 BV kit repurchase = ₹1000 to sponsor)
     */
    private function distributeSponsorAward(Member $purchaser, float $bv, $sourceType, $sourceId): ?array
    {
        $sponsor = $this->resolveSponsor($purchaser);
        
        if (!$sponsor) {
            return null;
        }
        
        // Sponsor Award: Use sponsor award percentage from settings
        $percentage = $this->sponsorAwardPercent;
        $amount = $bv * $percentage;
        $cappedAmount = $this->applyWeeklyCap($sponsor, $amount);
        
        if ($cappedAmount > 0) {
            $this->creditWallet($sponsor, $cappedAmount, 'SPONSOR_AWARD', $bv, $sourceType, $sourceId, $purchaser->id, [
                'percentage' => $percentage * 100,
                'original_amount' => $amount,
                'capped' => $amount !== $cappedAmount,
                'repurchase_by' => $purchaser->id,
            ]);
        }
        
        return [
            'sponsor_id' => $sponsor->id,
            'purchaser_id' => $purchaser->id,
            'amount' => $cappedAmount,
            'original_amount' => $amount,
            'capped' => $amount !== $cappedAmount,
        ];
    }

    /**
     * Get income description
     */
    private function getIncomeDescription(string $type, array $meta): string
    {
        return match($type) {
            'SELF' => 'Self purchase income (' . ($meta['percentage'] ?? 10) . '%)',
            'SPONSOR' => 'Sponsor income from ' . ($meta['from_member'] ?? 'downline') . ' (' . ($meta['percentage'] ?? 20) . '%)',
            'MATCHING' => 'Binary matching income (' . ($meta['percentage'] ?? 10) . '%)',
            'REWARD' => 'Reward income (' . ($meta['percentage'] ?? 20) . '%)',
            'REPURCHASE_SELF' => 'Repurchase self income (' . ($meta['percentage'] ?? 10) . '%)',
            'REPURCHASE_MATCHING' => 'Repurchase matching income (' . ($meta['percentage'] ?? 10) . '%)',
            'REPURCHASE_REWARD' => 'Repurchase reward income (' . ($meta['percentage'] ?? 20) . '%)',
            'SPONSOR_AWARD' => 'Sponsor award from repurchase (' . ($meta['percentage'] ?? 20) . '%)',
            default => 'Income',
        };
    }
}
