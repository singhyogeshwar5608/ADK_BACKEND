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
                'meta' => [
                    'left_bv_matched' => $leftDeduction,
                    'right_bv_matched' => $rightDeduction,
                    'match_ratio' => $result['match_ratio'],
                    'is_first_match' => $isFirstMatch,
                    'percentage' => $this->matchingIncomePercent * 100,
                    'original_amount' => $incomeAmount,
                    'capped' => $result['capped'],
                ],
                'is_capped' => $result['capped'],
            ]);

            WalletTransaction::create([
                'member_id' => $locked->id,
                'type' => 'CREDIT',
                'amount' => $cappedAmount,
                'balance_after' => $locked->wallet_balance,
                'reference' => 'MAT-' . now()->timestamp . '-' . $locked->id,
                'context' => $type . '_INCOME',
                'meta' => [
                    'left_bv_matched' => $leftDeduction,
                    'right_bv_matched' => $rightDeduction,
                    'match_ratio' => $result['match_ratio'],
                ],
            ]);

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
