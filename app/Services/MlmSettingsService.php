<?php

namespace App\Services;

use App\Models\MlmSetting;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MlmSettingsService
{
    private const CACHE_KEY = 'mlm_settings.binary';

    /**
     * Start date of the current income cycle, derived from the admin-managed
     * `income_cycle_start_day` setting (defaults to the 1st of the month).
     *
     * The cycle runs from the configured day of the current period through the
     * day before that same day in the previous period. This is the single
     * source of truth used everywhere income is aggregated (profile income
     * cards, income history, income statistics, downline tier). It replaces the
     * previous temporary `now()->startOfMonth()->subMonth()` hack, so each
     * month shows its own income and there is no double-counting across two
     * months at any point in the month.
     */
    public function getIncomeCycleStart(): Carbon
    {
        $day = (int) $this->getSetting('income_cycle_start_day', 1);
        if ($day < 1 || $day > 28) {
            $day = 1;
        }

        $now = Carbon::now();

        return $now->copy()
            ->startOfMonth()
            ->day($day);
    }

    /**
     * Start date of the income cycle for a given month, derived from the
     * admin-managed `income_cycle_start_day` setting. Used by the monthly
     * process so income aggregation, tier date and snapshot window all share
     * the same cycle boundaries (e.g. with day = 5, the September cycle is
     * Sep 5 -> Oct 4). With day = 1 this is identical to the calendar month,
     * preserving the previous behaviour.
     */
    public function getIncomeCycleStartForMonth(string $month): Carbon
    {
        $day = (int) $this->getSetting('income_cycle_start_day', 1);
        if ($day < 1 || $day > 28) {
            $day = 1;
        }

        return Carbon::createFromFormat('Y-m', $month)
            ->startOfMonth()
            ->day($day)
            ->startOfDay();
    }

    /**
     * End date (last moment) of the income cycle that contains the given month,
     * derived from the admin-managed `income_cycle_start_day` setting.
     *
     * A cycle starts on the configured day of a period and ends the day before
     * that same day in the following period (e.g. with `income_cycle_start_day`
     * = 5, the September tier is back-dated to Oct 4 = 23:59:59, the end of the
     * Sep 5 -> Oct 4 cycle). This makes the tier date "soft": it always follows
     * whatever cycle day the admin sets, so the tier lands in its own cycle
     * regardless of the configured start day.
     */
    public function getIncomeCycleEndForMonth(string $month): Carbon
    {
        $day = (int) $this->getSetting('income_cycle_start_day', 1);
        if ($day < 1 || $day > 28) {
            $day = 1;
        }

        return Carbon::createFromFormat('Y-m', $month)
            ->startOfMonth()
            ->day($day)
            ->addMonthNoOverflow()
            ->subDay()
            ->endOfDay();
    }

    /**
     * Total MONTHLY_TIER_DEDUCTION debit for a member bucketed by income cycle.
     *
     * The debit lives in wallet_transactions (context MONTHLY_TIER_DEDUCTION)
     * and its `created_at` is written as the cycle end by the monthly process,
     * so it cannot be filtered by a simple date window. Instead we bucket by the
     * `meta.month` value on the debit, which unambiguously identifies the income
     * cycle it belongs to (2026-07 = July cycle, 2026-08 = August cycle, ...).
     * This stays correct regardless of the admin-managed `income_cycle_start_day`.
     */
    public function getMonthlyTierDebitByCycle(int $memberId): array
    {
        $debitsByMonth = [];

        $debits = WalletTransaction::where('member_id', $memberId)
            ->where('context', 'MONTHLY_TIER_DEDUCTION')
            ->where('type', 'DEBIT')
            ->get();

        foreach ($debits as $debit) {
            $meta = is_array($debit->meta) ? $debit->meta : json_decode((string) $debit->meta, true);
            $month = $meta['month'] ?? null;
            if (!is_string($month)) {
                continue;
            }
            $debitsByMonth[$month] = round(($debitsByMonth[$month] ?? 0.0) + (float) $debit->amount, 2);
        }

        return $debitsByMonth;
    }

    public function getBinarySettings(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            $records = MlmSetting::query()
                ->whereIn('key', ['binary_commission_rate', 'pairing_ratio', 'max_daily_income'])
                ->get()
                ->keyBy('key');

            $commission = (float) data_get($records['binary_commission_rate']?->value, 'value', 0.10);
            $pairing = $records['pairing_ratio']?->value ?? ['left' => 1, 'right' => 1];
            $maxDaily = data_get($records['max_daily_income']?->value, 'value');

            return [
                'commission_rate' => $commission,
                'pairing_left' => (float) ($pairing['left'] ?? 1),
                'pairing_right' => (float) ($pairing['right'] ?? 1),
                'max_daily_income' => $maxDaily !== null ? (float) $maxDaily : null,
            ];
        });
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Cache::remember("mlm_setting.{$key}", now()->addMinutes(5), function () use ($key, $default) {
            $setting = MlmSetting::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            $value = $setting->value;
            
            // Handle different value types
            if (is_array($value)) {
                return data_get($value, 'value', $default);
            }
            
            return $value ?? $default;
        });
    }

    public function refreshBinarySettingsCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
