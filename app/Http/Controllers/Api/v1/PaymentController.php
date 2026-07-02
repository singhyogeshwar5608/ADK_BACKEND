<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\MlmSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Get Razorpay configuration from database
     */
    private function getRazorpayConfig(): array
    {
        return Cache::remember('razorpay_config', 3600, function () {
            $keyId = MlmSetting::where('key', 'razorpay_key_id')->first();
            $keySecret = MlmSetting::where('key', 'razorpay_key_secret')->first();

            if (!$keyId || !$keySecret) {
                throw new \Exception('Razorpay credentials not configured');
            }

            // Handle both string and array value formats
            $keyIdData = is_string($keyId->value) ? json_decode($keyId->value, true) : $keyId->value;
            $keySecretData = is_string($keySecret->value) ? json_decode($keySecret->value, true) : $keySecret->value;

            $keyIdValue = $keyIdData['value'] ?? null;
            $keySecretValue = $keySecretData['value'] ?? null;

            if (!$keyIdValue || !$keySecretValue) {
                throw new \Exception('Invalid Razorpay credentials');
            }

            return [
                'key_id' => $keyIdValue,
                'key_secret' => $keySecretValue,
            ];
        });
    }

    /**
     * Create Razorpay order
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            // Log incoming request for debugging
            Log::info('Payment order request received', [
                'amount' => $request->input('amount'),
                'all_data' => $request->all(),
            ]);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|in:INR,USD',
                'receipt' => 'nullable|string',
                'notes' => 'nullable|array',
            ]);

            try {
                $config = $this->getRazorpayConfig();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay not configured: ' . $e->getMessage(),
                    'debug_info' => 'Please configure Razorpay keys in admin panel: http://localhost:8000/update-razorpay.php',
                ], 400);
            }

            // Convert amount to paise (Razorpay expects amount in smallest currency unit)
            $amountInPaise = (int)($validated['amount'] * 100);

            // Create Razorpay order
            $response = Http::withBasicAuth($config['key_id'], $config['key_secret'])
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountInPaise,
                    'currency' => $validated['currency'] ?? 'INR',
                    'receipt' => $validated['receipt'] ?? 'order_' . time(),
                    'notes' => $validated['notes'] ?? [],
                ]);

            if (!$response->successful()) {
                Log::error('Razorpay order creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to create Razorpay order');
            }

            $orderData = $response->json();

            return response()->json([
                'success' => true,
                'order_id' => $orderData['id'],
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'],
                'key_id' => $config['key_id'], // Send key_id to frontend for Razorpay checkout
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Payment order creation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify Razorpay payment signature
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $config = $this->getRazorpayConfig();

            // Generate signature
            $generatedSignature = hash_hmac(
                'sha256',
                $validated['razorpay_order_id'] . '|' . $validated['razorpay_payment_id'],
                $config['key_secret']
            );

            if ($generatedSignature !== $validated['razorpay_signature']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment signature',
                ], 400);
            }

            // Fetch payment details from Razorpay
            $response = Http::withBasicAuth($config['key_id'], $config['key_secret'])
                ->get("https://api.razorpay.com/v1/payments/{$validated['razorpay_payment_id']}");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch payment details');
            }

            $paymentData = $response->json();

            return response()->json([
                'success' => true,
                'payment_id' => $paymentData['id'],
                'order_id' => $paymentData['order_id'],
                'amount' => $paymentData['amount'] / 100, // Convert from paise to rupees
                'status' => $paymentData['status'],
                'method' => $paymentData['method'],
            ]);

        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Razorpay key ID for frontend
     */
    public function getKeyId(): JsonResponse
    {
        try {
            $config = $this->getRazorpayConfig();

            return response()->json([
                'success' => true,
                'key_id' => $config['key_id'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear Razorpay config cache (call this when settings are updated)
     */
    public function clearCache(): JsonResponse
    {
        Cache::forget('razorpay_config');

        return response()->json([
            'success' => true,
            'message' => 'Razorpay config cache cleared',
        ]);
    }
}
