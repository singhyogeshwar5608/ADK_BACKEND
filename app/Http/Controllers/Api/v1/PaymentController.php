<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Member;
use App\Models\MlmSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\BvService;
use App\Services\ShiprocketService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private readonly BvService $bvService,
        private readonly ShiprocketService $shiprocketService,
        private readonly WhatsAppService $whatsAppService
    ) {
    }

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
     * Verify a successful Razorpay payment and persist the order.
     *
     * Called by the Flutter app right after a successful payment. Verifies the
     * payment against Razorpay (signature on web, server-side payment fetch on
     * mobile where the SDK does not return a signature), then creates the order
     * row so it shows up in the admin panel.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.bv' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'total_bv' => 'required|numeric|min:0',
            'shipping_address' => 'required|array',
            'member_snapshot' => 'nullable|array',
            'member_checkout' => 'nullable|boolean',
        ]);

        try {
            $config = $this->getRazorpayConfig();

            $payment = $this->verifyRazorpayPayment(
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature'] ?? null,
                $config,
                (float) $validated['total'],
            );

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                ], 400);
            }

            // Idempotency: never create a second order for the same payment.
            $existing = Order::where('razorpay_payment_id', $validated['razorpay_payment_id'])->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order already confirmed',
                    'order' => new OrderResource($existing),
                ]);
            }

            $member = auth('sanctum')->user();
            $linkMember = $request->boolean('member_checkout') && $member instanceof Member;

            $order = DB::transaction(function () use ($validated, $member, $linkMember) {
                $items = [];
                $subtotal = 0.0;
                $totalBv = 0.0;

                foreach ($validated['items'] as $item) {
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    $product->decrement('stock', $item['quantity']);

                    $price = (float) $item['price'];
                    $bv = (float) ($item['bv'] ?? $product->bv);
                    $itemTotal = $price * $item['quantity'];
                    $itemBv = $bv * $item['quantity'];

                    $subtotal += $itemTotal;
                    $totalBv += $itemBv;

                    $items[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'price' => $price,
                        'bv' => $bv,
                        'total' => $itemTotal,
                        'total_bv' => $itemBv,
                        'hsn_code' => $product->hsn_code,
                    ];
                }

                $snapshot = $linkMember ? [
                    'memberId' => $member->member_id,
                    'fullName' => $member->full_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'serialNo' => $member->serial_no,
                ] : array_filter([
                    'memberId' => $validated['member_snapshot']['member_id'] ?? null,
                    'fullName' => $validated['member_snapshot']['full_name'] ?? null,
                    'email' => $validated['member_snapshot']['email'] ?? null,
                    'phone' => $validated['member_snapshot']['phone'] ?? null,
                    'serialNo' => null,
                ], fn ($value) => $value !== null);

                return Order::create([
                    'member_id' => $linkMember ? $member->id : null,
                    'member_snapshot' => $snapshot,
                    'items' => $items,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $subtotal + (float) ($validated['tax'] ?? 0),
                    'total_bv' => $totalBv,
                    'status' => 'PENDING',
                    'payment_method' => 'razorpay',
                    'payment_status' => 'PAID',
                    'shipping_address' => $validated['shipping_address'],
                    'razorpay_order_id' => $validated['razorpay_order_id'],
                    'razorpay_payment_id' => $validated['razorpay_payment_id'],
                    'razorpay_signature' => $validated['razorpay_signature'] ?? null,
                    'history' => [
                        [
                            'status' => 'PENDING',
                            'payment_status' => 'PENDING',
                            'changedBy' => 'system',
                            'changedAt' => now()->toIso8601String(),
                            'note' => 'Order created, awaiting payment confirmation',
                        ],
                        [
                            'status' => 'PENDING',
                            'payment_status' => 'PAID',
                            'changedBy' => 'razorpay',
                            'changedAt' => now()->toIso8601String(),
                            'note' => "Payment received ({$validated['razorpay_payment_id']})",
                        ],
                    ],
                ]);
            });

            if ($order->total_bv > 0) {
                try {
                    $this->bvService->awardForOrder($order);
                } catch (\Throwable $e) {
                    Log::error('Payment confirm: BV award failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $this->shiprocketService->createOrder($order);
            } catch (\Throwable $e) {
                Log::error('Payment confirm: Shiprocket sync failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $refreshed = $order->refresh();
            $whatsAppLink = null;
            try {
                $this->whatsAppService->sendOrderNotification($refreshed);
                $whatsAppLink = $this->whatsAppService->getOrderWhatsAppLink($refreshed);
            } catch (\Throwable $e) {
                Log::error('Payment confirm: WhatsApp notify failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order confirmed',
                'order' => new OrderResource($refreshed),
                'whatsapp_link' => $whatsAppLink,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Payment confirmation error', [
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
     * Verify a payment against Razorpay.
     *
     * @return array<string, mixed>|null
     */
    private function verifyRazorpayPayment(
        string $orderId,
        string $paymentId,
        ?string $signature,
        array $config,
        float $expectedTotal
    ): ?array {
        if ($signature !== null && $signature !== '') {
            $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $config['key_secret']);
            if (!hash_equals($generated, $signature)) {
                Log::warning('Razorpay signature mismatch', compact('orderId', 'paymentId'));
                return null;
            }
        }

        $response = Http::withBasicAuth($config['key_id'], $config['key_secret'])
            ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            Log::warning('Razorpay payment fetch failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);
            return null;
        }

        $payment = $response->json();

        if (($payment['order_id'] ?? null) !== $orderId) {
            Log::warning('Razorpay payment/order mismatch', compact('orderId', 'paymentId'));
            return null;
        }

        if (!in_array($payment['status'] ?? null, ['captured', 'authorized'], true)) {
            Log::warning('Razorpay payment not captured', [
                'payment_id' => $paymentId,
                'status' => $payment['status'] ?? null,
            ]);
            return null;
        }

        $paidAmount = (int) ($payment['amount'] ?? 0);
        $expectedPaise = (int) round($expectedTotal * 100);
        if ($paidAmount !== $expectedPaise) {
            Log::warning('Razorpay amount mismatch', [
                'payment_id' => $paymentId,
                'paid' => $paidAmount,
                'expected' => $expectedPaise,
            ]);
            return null;
        }

        return $payment;
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
