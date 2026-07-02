<?php

namespace App\Services;

use App\Models\IncomeLedger;
use App\Models\Member;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeService
{
    public function applyBinaryIncome(Member $member, array $settings = []): void
    {
        Log::info('IncomeService: Starting binary income application', [
            'member_id' => $member->id,
            'member_wallet_balance' => $member->wallet_balance,
            'member_total_earned' => $member->wallet_total_earned,
            'bv_left_leg' => $member->bv_left_leg,
            'bv_right_leg' => $member->bv_right_leg,
            'settings' => $settings,
        ]);

        $pairingLeft = max(0.0001, (float) ($settings['pairing_left'] ?? 1));
        $pairingRight = max(0.0001, (float) ($settings['pairing_right'] ?? 1));
        $commission = max(0, (float) ($settings['commission_rate'] ?? 0.10));
        $maxDaily = $settings['max_daily_income'] ?? null;

        DB::transaction(function () use ($member, $pairingLeft, $pairingRight, $commission, $maxDaily) {
            /** @var Member|null $locked */
            $locked = Member::query()->lockForUpdate()->find($member->id);
            if (!$locked) {
                return;
            }

            $availableLeft = (float) $locked->bv_carry_forward_left;
            $availableRight = (float) $locked->bv_carry_forward_right;

            Log::info('IncomeService: BV availability check', [
                'member_id' => $locked->id,
                'available_left' => $availableLeft,
                'available_right' => $availableRight,
                'pairing_left' => $pairingLeft,
                'pairing_right' => $pairingRight,
            ]);

            if ($availableLeft <= 0 || $availableRight <= 0) {
                Log::warning('IncomeService: Insufficient BV for pairing', [
                    'member_id' => $locked->id,
                    'available_left' => $availableLeft,
                    'available_right' => $availableRight,
                ]);
                return;
            }

            $pairUnits = min($availableLeft / $pairingLeft, $availableRight / $pairingRight);
            if ($pairUnits <= 0) {
                Log::warning('IncomeService: No pairing units calculated', [
                    'member_id' => $locked->id,
                    'pair_units' => $pairUnits,
                ]);
                return;
            }

            $matchedLeft = $pairUnits * $pairingLeft;
            $matchedRight = $pairUnits * $pairingRight;
            $commissionBase = min($matchedLeft, $matchedRight);
            $incomeAmount = $commissionBase * $commission;

            Log::info('IncomeService: Income calculation', [
                'member_id' => $locked->id,
                'pair_units' => $pairUnits,
                'matched_left' => $matchedLeft,
                'matched_right' => $matchedRight,
                'commission_base' => $commissionBase,
                'commission_rate' => $commission,
                'income_amount' => $incomeAmount,
            ]);

            if ($incomeAmount <= 0) {
                Log::warning('IncomeService: Zero or negative income', [
                    'member_id' => $locked->id,
                    'income_amount' => $incomeAmount,
                ]);
                return;
            }

            if ($maxDaily !== null) {
                $earnedToday = IncomeLedger::query()
                    ->where('member_id', $locked->id)
                    ->whereDate('created_at', Carbon::today())
                    ->sum('amount');

                $remainingCap = (float) $maxDaily - (float) $earnedToday;
                if ($remainingCap <= 0) {
                    return;
                }

                if ($incomeAmount > $remainingCap) {
                    $scale = $remainingCap / $incomeAmount;
                    $incomeAmount = $remainingCap;
                    $matchedLeft *= $scale;
                    $matchedRight *= $scale;
                }
            }

            $oldWalletBalance = $locked->wallet_balance;
            $oldTotalEarned = $locked->wallet_total_earned;
            $oldBvLeft = $locked->bv_carry_forward_left;
            $oldBvRight = $locked->bv_carry_forward_right;

            $locked->bv_carry_forward_left = max(0, $availableLeft - $matchedLeft);
            $locked->bv_carry_forward_right = max(0, $availableRight - $matchedRight);
            $locked->wallet_balance = (float) $locked->wallet_balance + $incomeAmount;
            $locked->wallet_total_earned = (float) $locked->wallet_total_earned + $incomeAmount;
            $locked->save();

            Log::info('IncomeService: Member wallet updated', [
                'member_id' => $locked->id,
                'old_wallet_balance' => $oldWalletBalance,
                'new_wallet_balance' => $locked->wallet_balance,
                'old_total_earned' => $oldTotalEarned,
                'new_total_earned' => $locked->wallet_total_earned,
                'income_added' => $incomeAmount,
                'old_bv_left' => $oldBvLeft,
                'new_bv_left' => $locked->bv_carry_forward_left,
                'old_bv_right' => $oldBvRight,
                'new_bv_right' => $locked->bv_carry_forward_right,
            ]);

            $meta = [
                'consumed_left' => $matchedLeft,
                'consumed_right' => $matchedRight,
            ];

            IncomeLedger::create([
                'member_id' => $locked->id,
                'amount' => $incomeAmount,
                'type' => 'BINARY',
                'source_type' => 'BV_PAIRING',
                'source_id' => (string) $locked->id,
                'meta' => $meta,
            ]);

            Log::info('IncomeService: Income ledger created', [
                'member_id' => $locked->id,
                'amount' => $incomeAmount,
                'type' => 'BINARY',
                'source_type' => 'BV_PAIRING',
            ]);

            WalletTransaction::create([
                'member_id' => $locked->id,
                'type' => 'CREDIT',
                'amount' => $incomeAmount,
                'balance_after' => $locked->wallet_balance,
                'reference' => 'BIN-' . now()->timestamp . '-' . $locked->id,
                'context' => 'BINARY_INCOME',
                'meta' => $meta,
            ]);

            Log::info('IncomeService: Wallet transaction created', [
                'member_id' => $locked->id,
                'amount' => $incomeAmount,
                'balance_after' => $locked->wallet_balance,
                'reference' => 'BIN-' . now()->timestamp . '-' . $locked->id,
            ]);

            Log::info('IncomeService: Binary income application completed', [
                'member_id' => $locked->id,
                'final_wallet_balance' => $locked->wallet_balance,
                'final_total_earned' => $locked->wallet_total_earned,
            ]);
        });
    }
}
