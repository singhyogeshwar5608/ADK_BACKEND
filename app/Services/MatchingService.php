<?php

namespace App\Services;

use App\Models\IncomeTransaction;
use App\Models\MatchingHistory;
use App\Models\Member;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    private int $weeklyCap;
    private float $matchingIncomePercent;

    public function __construct(
        private readonly MlmSettingsService $settingsService
    ) {
        $this->loadMatchingSettings();
    }

    /**
     * Load matching settings from database
     */
    private function loadMatchingSettings(): void
    {
        $this->weeklyCap = (int) $this->settingsService->getSetting('weekly_capping', 50000);
        $this->matchingIncomePercent = (float) $this->settingsService->getSetting('matching_income_percent', 10) / 100;
    }

    /**
     * Calculate and distribute matching income for a member (1:1 ratio for all matches)
     */
    public function calculateAndDistributeMatching(Member $member, bool $isRepurchase = false): array
    {
        $this->loadMatchingSettings();

        $result = [
            'member_id' => $member->id,
            'income_earned' => 0,
            'left_bv_matched' => 0,
            'right_bv_matched' => 0,
            'matched_bv' => 0,
            'percentage_applied' => 0,
            'is_first_match' => false,
            'match_ratio' => '1:1',
            'capped' => false,
        ];

        DB::transaction(function () use ($member, $isRepurchase, &$result) {
            $locked = Member::lockForUpdate()->find($member->id);
            
            if (!$locked) {
                return;
            }

            $leftBV = (float) $locked->bv_carry_forward_left;
            $rightBV = (float) $locked->bv_carry_forward_right;

            if ($leftBV <= 0 || $rightBV <= 0) {
                return;
            }

            $isFirstMatch = !$locked->first_match_done;
            $result['is_first_match'] = $isFirstMatch;

            $matchedBV = min($leftBV, $rightBV);

            if ($matchedBV <= 0) {
                return;
            }

            $incomeAmount = $matchedBV * $this->matchingIncomePercent;

            Log::info('MatchingService: Percentage & Income', [
                'member_id' => $locked->id,
                'isRepurchase' => $isRepurchase,
                'percentage_used' => $this->matchingIncomePercent,
                'matchedBV' => $matchedBV,
                'incomeAmount' => $incomeAmount,
            ]);

            $cappedAmount = $this->applyWeeklyCap($locked, $incomeAmount);
            
            if ($cappedAmount <= 0) {
                return;
            }

            $actualMatchedBV = $matchedBV;
            if ($cappedAmount < $incomeAmount) {
                $actualMatchedBV = $matchedBV * ($cappedAmount / $incomeAmount);
                $result['capped'] = true;
            }

            $leftDeduction = min($leftBV, $actualMatchedBV);
            $rightDeduction = min($rightBV, $actualMatchedBV);

            $locked->bv_carry_forward_left -= $leftDeduction;
            $locked->bv_carry_forward_right -= $rightDeduction;
            $matchedPairs = min($leftDeduction, $rightDeduction);
            
            Log::info('MatchingService: BV calculation details', [
                'member_id' => $locked->id,
                'left_bv_before' => $leftBV,
                'right_bv_before' => $rightBV,
                'is_first_match' => $isFirstMatch,
                'matched_bv_calculated' => $matchedBV,
                'left_deduction' => $leftDeduction,
                'right_deduction' => $rightDeduction,
                'matched_pairs' => $matchedPairs,
                'old_total_matched_bv' => $locked->total_matched_bv,
                'new_total_matched_bv' => $locked->total_matched_bv + $matchedPairs,
            ]);

            $locked->total_matched_bv += $matchedPairs;
            
            if ($isFirstMatch) {
                $locked->first_match_done = true;
            }

            // --- 5% Tier Deduction: deduct from member, credit to their direct sponsor ---
            $tierAmount = 0.0;
            $tierSponsor = null;

            $originalCappedAmount = $cappedAmount;

            if ($cappedAmount > 0) {
                $tierAmount = round($cappedAmount * 0.05, 2);
                if ($tierAmount > 0) {
                    if ($locked->referred_by) {
                        $tierSponsor = Member::where('member_id', $locked->referred_by)->lockForUpdate()->first();
                    }
                    if (!$tierSponsor && $locked->sponsor_id) {
                        $tierSponsor = Member::lockForUpdate()->find($locked->sponsor_id);
                    }
                    if ($tierSponsor) {
                        $cappedAmount = round($cappedAmount - $tierAmount, 2);
                    } else {
                        $tierAmount = 0.0;
                    }
                }
            }
            // ---------------------------------------------------------------------------

            // --- 10% Admin TDS Deduction: deduct from matching income, credit to admin ---
            $adminTdsAmount = 0.0;
            $admin = null;

            if ($cappedAmount > 0) {
                $originalForTds = $cappedAmount + $tierAmount;
                $adminTdsAmount = round($originalForTds * 0.10, 2);
                if ($adminTdsAmount > 0) {
                    $admin = Member::where('role', 'ADMIN')->lockForUpdate()->first();
                    if ($admin && $admin->id !== $locked->id) {
                        $cappedAmount = round($cappedAmount - $adminTdsAmount, 2);
                    } else {
                        $adminTdsAmount = 0.0;
                        $admin = null;
                    }
                }
            }
            // ---------------------------------------------------------------------------

            $locked->wallet_balance += $cappedAmount;
            $locked->wallet_total_earned += $cappedAmount;
            $locked->weekly_income += $cappedAmount;
            $locked->save();

            MatchingHistory::create([
                'member_id' => $locked->id,
                'left_bv_matched' => $leftDeduction,
                'right_bv_matched' => $rightDeduction,
                'matched_bv' => $matchedPairs,
                'income_earned' => $cappedAmount,
                'percentage_applied' => $this->matchingIncomePercent * 100,
                'is_first_match' => $isFirstMatch,
                'match_ratio' => $result['match_ratio'],
            ]);

            $tierMeta = $tierAmount > 0 ? ['tier_deducted' => $tierAmount, 'tier_sponsor_id' => $tierSponsor?->id] : [];
            if ($adminTdsAmount > 0 && $admin) {
                $tierMeta['admin_tds_deducted'] = $adminTdsAmount;
                $tierMeta['admin_tds_admin_id'] = $admin->id;
            }

            $type = $isRepurchase ? 'REPURCHASE_MATCHING' : 'MATCHING';
            IncomeTransaction::create([
                'member_id' => $locked->id,
                'type' => $type,
                'amount' => $cappedAmount,
                'bv' => $matchedPairs,
                'source_type' => 'BINARY_MATCHING',
                'source_id' => $locked->id,
                'from_member_id' => null,
                'description' => "Binary matching income ({$result['match_ratio']} ratio, " . ($this->matchingIncomePercent * 100) . "%)",
                'meta' => array_merge([
                    'left_bv_matched' => $leftDeduction,
                    'right_bv_matched' => $rightDeduction,
                    'match_ratio' => $result['match_ratio'],
                    'is_first_match' => $isFirstMatch,
                    'percentage' => $this->matchingIncomePercent * 100,
                    'original_amount' => $incomeAmount,
                    'capped' => $result['capped'],
                ], $tierMeta),
                'is_capped' => $result['capped'],
            ]);

            WalletTransaction::create([
                'member_id' => $locked->id,
                'type' => 'CREDIT',
                'amount' => $cappedAmount,
                'balance_after' => $locked->wallet_balance,
                'reference' => 'MAT-' . now()->timestamp . '-' . $locked->id,
                'context' => $type . '_INCOME',
                'meta' => array_merge([
                    'left_bv_matched' => $leftDeduction,
                    'right_bv_matched' => $rightDeduction,
                    'match_ratio' => $result['match_ratio'],
                ], $tierMeta),
            ]);

            // --- Credit 5% tier income to the sponsor (recursive — own 5% deducted too) ---
            if ($tierSponsor && $tierAmount > 0) {
                $originalAmount = $cappedAmount + $tierAmount + ($adminTdsAmount > 0 ? $adminTdsAmount : 0);
                $this->creditMatchingTierIncome($tierSponsor, $tierAmount, $originalAmount, $type, $locked->id);
            }
            // ---------------------------------------------

            // --- Credit 10% admin TDS income to admin ---
            if ($adminTdsAmount > 0 && $admin) {
                $this->creditMatchingAdminTds(
                    $admin,
                    $adminTdsAmount,
                    $type,
                    $locked->id,
                    $originalCappedAmount,
                );
            }
            // ---------------------------------------------

            $result['income_earned'] = $cappedAmount;
            $result['left_bv_matched'] = $leftDeduction;
            $result['right_bv_matched'] = $rightDeduction;
            $result['matched_bv'] = $actualMatchedBV;
            $result['percentage_applied'] = $this->matchingIncomePercent * 100;
        });

        return $result;
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
            \Carbon\Carbon::parse($member->weekly_income_reset_date)->addWeek()->isPast()) {
            
            $member->weekly_income = 0;
            $member->weekly_income_reset_date = \Carbon\Carbon::now()->toDateString();
            $member->save();
        }
    }

    /**
     * Recursively credit 5% tier income up the sponsorship chain.
     * Each level takes 5% of what the previous level received.
     * Also deducts 10% admin TDS at each level.
     */
    private function creditMatchingTierIncome(
        Member $sponsor,
        float $tierAmount,
        float $memberOriginalAmount,
        string $sourceIncomeType,
        int $fromMemberId,
        int $depth = 0
    ): void {
        if ($depth > 10 || $tierAmount <= 0) {
            return;
        }

        $this->resetWeeklyIncomeIfNeeded($sponsor);
        $remainingCap = $this->weeklyCap - (float) $sponsor->weekly_income;
        $cappedTier = $remainingCap > 0 ? min($tierAmount, $remainingCap) : 0.0;
        if ($cappedTier <= 0) {
            return;
        }

        // --- 10% Admin TDS Deduction ---
        $originalCappedTier = $cappedTier;
        $adminTdsAmount = round($originalCappedTier * 0.10, 2);
        $admin = Member::where('role', 'ADMIN')->lockForUpdate()->first();
        if ($admin && $admin->id !== $sponsor->id && $adminTdsAmount > 0) {
            $cappedTier = round($cappedTier - $adminTdsAmount, 2);
            $this->creditMatchingAdminTds(
                $admin,
                $adminTdsAmount,
                'TIER_INCOME',
                $sponsor->id,
                $originalCappedTier,
            );
        } else {
            $adminTdsAmount = 0.0;
        }
        // ---------------------------------

        // Credit this level
        $sponsor->wallet_balance += $cappedTier;
        $sponsor->wallet_total_earned += $cappedTier;
        $sponsor->weekly_income += $cappedTier;
        $sponsor->save();

        IncomeTransaction::create([
            'member_id' => $sponsor->id,
            'type' => 'TIER_INCOME',
            'amount' => $cappedTier,
            'bv' => 0,
            'source_type' => 'BINARY_MATCHING',
            'source_id' => $fromMemberId,
            'from_member_id' => $fromMemberId,
            'description' => "5% tier income from " . ($depth === 0 ? "downline's" : "sponsor chain's") . " {$sourceIncomeType} income",
            'meta' => [
                'source_member_id' => $fromMemberId,
                'source_income_type' => $sourceIncomeType,
                'source_income_amount' => $memberOriginalAmount,
                'tier_percentage' => 5,
                'original_amount' => $tierAmount,
                'capped' => $originalCappedTier < $tierAmount,
                'tier_depth' => $depth,
                'admin_tds_deducted' => $adminTdsAmount > 0 ? $adminTdsAmount : null,
            ],
            'is_capped' => $originalCappedTier < $tierAmount,
        ]);

        WalletTransaction::create([
            'member_id' => $sponsor->id,
            'type' => 'CREDIT',
            'amount' => $cappedTier,
            'balance_after' => $sponsor->wallet_balance,
            'reference' => 'TIR-' . now()->timestamp . '-' . $sponsor->id,
            'context' => 'TIER_INCOME',
            'meta' => [
                'from_member_id' => $fromMemberId,
                'source_income_type' => $sourceIncomeType,
                'tier_depth' => $depth,
                'admin_tds_deducted' => $adminTdsAmount > 0 ? $adminTdsAmount : null,
            ],
        ]);

        // Recurse: 5% of this tier amount (after admin TDS) goes to THIS sponsor's sponsor
        $nextTierAmount = round($cappedTier * 0.05, 2);
        if ($nextTierAmount > 0) {
            $nextSponsor = null;
            if ($sponsor->referred_by) {
                $nextSponsor = Member::where('member_id', $sponsor->referred_by)->lockForUpdate()->first();
            }
            if (!$nextSponsor && $sponsor->sponsor_id) {
                $nextSponsor = Member::lockForUpdate()->find($sponsor->sponsor_id);
            }
            if ($nextSponsor) {
                $this->creditMatchingTierIncome(
                    $nextSponsor,
                    $nextTierAmount,
                    $cappedTier,
                    $sourceIncomeType,
                    $sponsor->id,
                    $depth + 1
                );
            }
        }
    }

    /**
     * Credit admin with 10% TDS on matching income (or matching tier income).
     * No further deductions applied.
     */
    private function creditMatchingAdminTds(
        Member $admin,
        float $tdsAmount,
        string $sourceIncomeType,
        int $fromMemberId,
        float $originalIncomeAmount,
    ): void {
        $admin->wallet_balance += $tdsAmount;
        $admin->wallet_total_earned += $tdsAmount;
        $admin->tds_income += $tdsAmount;
        $admin->save();

        IncomeTransaction::create([
            'member_id' => $admin->id,
            'type' => 'TDS_INCOME',
            'amount' => $tdsAmount,
            'bv' => 0,
            'source_type' => 'BINARY_MATCHING',
            'source_id' => $fromMemberId,
            'from_member_id' => $fromMemberId,
            'description' => "10% admin TDS income on {$sourceIncomeType} from member #{$fromMemberId}",
            'meta' => [
                'source_income_type' => $sourceIncomeType,
                'source_income_amount' => $originalIncomeAmount,
                'source_member_id' => $fromMemberId,
                'tds_percentage' => 10,
            ],
            'is_capped' => false,
        ]);

        WalletTransaction::create([
            'member_id' => $admin->id,
            'type' => 'CREDIT',
            'amount' => $tdsAmount,
            'balance_after' => $admin->wallet_balance,
            'reference' => 'TDS-' . now()->timestamp . '-' . $admin->id,
            'context' => 'TDS_INCOME',
            'meta' => [
                'from_member_id' => $fromMemberId,
                'source_income_type' => $sourceIncomeType,
                'source_income_amount' => $originalIncomeAmount,
            ],
        ]);
    }

    /**
     * Get matching summary for a member
     */
    public function getMatchingSummary(Member $member): array
    {
        return [
            'member_id' => $member->id,
            'left_bv' => (float) $member->bv_left_leg,
            'right_bv' => (float) $member->bv_right_leg,
            'carry_forward_left' => (float) $member->bv_carry_forward_left,
            'carry_forward_right' => (float) $member->bv_carry_forward_right,
            'total_matched_bv' => (float) $member->total_matched_bv,
            'first_match_done' => (bool) $member->first_match_done,
            'next_match_ratio' => '1:1',
            'potential_match' => $this->calculatePotentialMatch($member),
        ];
    }

    /**
     * Calculate potential matching income (1:1 ratio)
     */
    private function calculatePotentialMatch(Member $member): array
    {
        $leftBV = (float) $member->bv_carry_forward_left;
        $rightBV = (float) $member->bv_carry_forward_right;
        
        if ($leftBV <= 0 || $rightBV <= 0) {
            return [
                'matched_bv' => 0,
                'matching_income' => 0,
                'matching_percent' => $this->matchingIncomePercent * 100,
            ];
        }
        
        $matchedBV = min($leftBV, $rightBV);
        
        return [
            'matched_bv' => $matchedBV,
            'matching_income' => $matchedBV * $this->matchingIncomePercent,
            'matching_percent' => $this->matchingIncomePercent * 100,
        ];
    }
}
