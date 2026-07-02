<?php

namespace App\Services;

use App\Models\MlmSetting;
use Illuminate\Support\Facades\Cache;

class MlmSettingsService
{
    private const CACHE_KEY = 'mlm_settings.binary';

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
