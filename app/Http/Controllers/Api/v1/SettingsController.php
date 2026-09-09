<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MlmSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    private const CACHE_KEY = 'app_settings.all';
    private const RAZORPAY_KEYS = ['razorpay_key_id', 'razorpay_key_secret'];

    public function index(): JsonResponse
    {
        $settings = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            return MlmSetting::query()
                ->whereIn('key', [
                    'binary_commission_rate',
                    'pairing_ratio',
                    'max_daily_income',
                    'razorpay_key_id',
                    'razorpay_key_secret',
                    'joining_amount',
                    'business_volume',
                    'self_income_percent',
                    'direct_income_percent',
                    'matching_income_percent',
                    'self_repurchase_income_percent',
                    'repurchase_matching_income_percent',
                    'award_income_percent',
                    'repurchase_amount',
                    'repurchase_bv',
                    'weekly_capping',
                    'income_cycle_start_day',
                ])
                ->get()
                ->keyBy('key');
        });

        // Hide secret values in response
        $response = $settings->map(function ($setting) {
            $value = $setting->value;
            
            // Hide secret keys
            if (in_array($setting->key, ['razorpay_key_secret'])) {
                $value = $value ? '********' : null;
            }

            return [
                'key' => $setting->key,
                'value' => $value,
                'updated_at' => $setting->updated_at,
            ];
        });

        return response()->json([
            'settings' => $response,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        // Handle OPTIONS preflight request
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            ]);
        }

        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'in:' . implode(',', [
                'binary_commission_rate',
                'pairing_ratio', 
                'max_daily_income',
                'razorpay_key_id',
                'razorpay_key_secret',
                'joining_amount',
                'business_volume',
                'self_income_percent',
                'direct_income_percent',
                'matching_income_percent',
                'self_repurchase_income_percent',
                'repurchase_matching_income_percent',
                'award_income_percent',
                'repurchase_amount',
                'repurchase_bv',
                'weekly_capping',
                'income_cycle_start_day',
            ])],
            'settings.*.value' => ['required'],
        ]);

        $updatedSettings = [];

        foreach ($validated['settings'] as $settingData) {
            $key = $settingData['key'];
            $value = $settingData['value'];

            // Validate specific setting types
            $this->validateSettingValue($key, $value);

            MlmSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? $value : ['value' => $value]]
            );

            $updatedSettings[] = $key;

            // Clear per-key cache used by MlmSettingsService::getSetting
            Cache::forget("mlm_setting.{$key}");
        }

        // Clear main settings cache
        Cache::forget(self::CACHE_KEY);
        
        // Clear Razorpay config cache if Razorpay keys were updated
        if (in_array('razorpay_key_id', $updatedSettings) || in_array('razorpay_key_secret', $updatedSettings)) {
            Cache::forget('razorpay_config');
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'updated_settings' => $updatedSettings,
        ]);
    }

    private function validateSettingValue(string $key, mixed $value): void
    {
        match ($key) {
            'binary_commission_rate' => $this->validateCommissionRate($value),
            'pairing_ratio' => $this->validatePairingRatio($value),
            'max_daily_income' => $this->validateMaxDailyIncome($value),
            'razorpay_key_id' => $this->validateRazorpayKeyId($value),
            'razorpay_key_secret' => $this->validateRazorpayKeySecret($value),
            'joining_amount', 'business_volume', 'repurchase_amount', 'repurchase_bv', 'weekly_capping' => $this->validatePositiveNumber($value),
            'income_cycle_start_day' => $this->validateCycleStartDay($value),
            'self_income_percent', 'direct_income_percent', 'matching_income_percent', 'self_repurchase_income_percent', 'repurchase_matching_income_percent', 'award_income_percent' => $this->validatePercentage($value),
            default => throw ValidationException::withMessages([
                'key' => "Unknown setting key: {$key}"
            ])
        };
    }

    private function validateCommissionRate(mixed $value): void
    {
        $rate = is_array($value) ? $value['value'] : $value;
        
        if (!is_numeric($rate) || $rate < 0 || $rate > 1) {
            throw ValidationException::withMessages([
                'value' => 'Commission rate must be between 0 and 1'
            ]);
        }
    }

    private function validatePairingRatio(mixed $value): void
    {
        if (!is_array($value) || !isset($value['left'], $value['right'])) {
            throw ValidationException::withMessages([
                'value' => 'Pairing ratio must have left and right values'
            ]);
        }

        if (!is_numeric($value['left']) || $value['left'] < 1 ||
            !is_numeric($value['right']) || $value['right'] < 1) {
            throw ValidationException::withMessages([
                'value' => 'Pairing ratio values must be at least 1'
            ]);
        }
    }

    private function validateMaxDailyIncome(mixed $value): void
    {
        $income = is_array($value) ? $value['value'] : $value;
        
        if ($income !== null && (!is_numeric($income) || $income < 0)) {
            throw ValidationException::withMessages([
                'value' => 'Max daily income must be a positive number or null'
            ]);
        }
    }

    private function validateRazorpayKeyId(mixed $value): void
    {
        $keyId = is_array($value) ? $value['value'] : $value;
        
        if (!is_string($keyId) || empty($keyId)) {
            throw ValidationException::withMessages([
                'value' => 'Razorpay Key ID is required'
            ]);
        }

        // Accept both test and live keys: rzp_test_xxx or rzp_live_xxx or rzp_xxx
        if (!preg_match('/^rzp_(test_|live_)?[a-zA-Z0-9_]+$/', $keyId)) {
            throw ValidationException::withMessages([
                'value' => 'Invalid Razorpay Key ID format (must start with rzp_)'
            ]);
        }
    }

    private function validateRazorpayKeySecret(mixed $value): void
    {
        $secret = is_array($value) ? $value['value'] : $value;
        
        if (!is_string($secret) || strlen($secret) < 10) {
            throw ValidationException::withMessages([
                'value' => 'Razorpay Key Secret must be at least 10 characters'
            ]);
        }
    }

    private function validatePositiveNumber(mixed $value): void
    {
        $num = is_array($value) ? $value['value'] : $value;
        
        if (!is_numeric($num) || $num < 0) {
            throw ValidationException::withMessages([
                'value' => 'Value must be a positive number'
            ]);
        }
    }

    private function validatePercentage(mixed $value): void
    {
        $percent = is_array($value) ? $value['value'] : $value;

        if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
            throw ValidationException::withMessages([
                'value' => 'Percentage must be between 0 and 100'
            ]);
        }
    }

    private function validateCycleStartDay(mixed $value): void
    {
        $day = is_array($value) ? $value['value'] : $value;

        if (!is_numeric($day) || (int) $day < 1 || (int) $day > 28) {
            throw ValidationException::withMessages([
                'value' => 'Income cycle start day must be an integer between 1 and 28'
            ]);
        }
    }
}