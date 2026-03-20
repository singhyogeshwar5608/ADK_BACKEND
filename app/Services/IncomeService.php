<?php

namespace App\Services;

use App\Models\IncomeLedger;
use App\Models\Member;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeService
{
    public function applyBinaryIncome(Member $member, array $settings = []): void
    {
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

            if ($availableLeft <= 0 || $availableRight <= 0) {
                return;
            }

            $pairUnits = min($availableLeft / $pairingLeft, $availableRight / $pairingRight);
            if ($pairUnits <= 0) {
                return;
            }

            $matchedLeft = $pairUnits * $pairingLeft;
            $matchedRight = $pairUnits * $pairingRight;
            $commissionBase = min($matchedLeft, $matchedRight);
            $incomeAmount = $commissionBase * $commission;

            if ($incomeAmount <= 0) {
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

            $locked->bv_carry_forward_left = max(0, $availableLeft - $matchedLeft);
            $locked->bv_carry_forward_right = max(0, $availableRight - $matchedRight);
            $locked->wallet_balance = (float) $locked->wallet_balance + $incomeAmount;
            $locked->wallet_total_earned = (float) $locked->wallet_total_earned + $incomeAmount;
            $locked->save();

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

            WalletTransaction::create([
                'member_id' => $locked->id,
                'type' => 'CREDIT',
                'amount' => $incomeAmount,
                'balance_after' => $locked->wallet_balance,
                'reference' => 'BIN-' . now()->timestamp . '-' . $locked->id,
                'context' => 'BINARY_INCOME',
                'meta' => $meta,
            ]);
        });
    }
}
