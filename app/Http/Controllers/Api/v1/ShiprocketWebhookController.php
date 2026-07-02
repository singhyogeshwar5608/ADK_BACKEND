<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShiprocketWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiprocketWebhookController extends Controller
{
    public function __construct(
        private readonly \App\Services\ShiprocketService $shiprocketService
    ) {}

    /**
     * Handle Shiprocket Webhook
     */
    public function handle(Request $request)
    {
        try {
            Log::info('Shiprocket Webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // 1. Validate x-api-key
            $webhookKey = config('services.shiprocket.webhook_key');
            $providedKey = $request->header('x-api-key');

            if ($providedKey !== $webhookKey) {
                Log::warning('Unauthorized Shiprocket Webhook attempt', [
                    'provided' => $providedKey,
                    'ip' => $request->ip()
                ]);
                
                if ($request->isEmpty() || empty($request->all())) {
                    return response()->json(['success' => true, 'message' => 'Test connection successful']);
                }

                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $payload = $request->all();
            
            if (empty($payload)) {
                return response()->json(['success' => true, 'message' => 'Test successful']);
            }

            // 2. Log Webhook
            ShiprocketWebhookLog::create([
                'order_id' => $payload['order_id'] ?? null,
                'awb' => $payload['awb'] ?? null,
                'event' => $payload['current_status'] ?? 'unknown',
                'payload' => $payload
            ]);

            // 3. Sync Status with Order
            if (isset($payload['order_id'])) {
                // Shiprocket order_id might be 'ORD-123-1622000000', we need the middle numeric part
                $parts = explode('-', $payload['order_id']);
                $localOrderId = $parts[1] ?? null;

                if (!$localOrderId) {
                    Log::warning('Shiprocket Webhook: Could not extract local order ID', ['order_id' => $payload['order_id']]);
                    return response()->json(['success' => false, 'message' => 'Invalid order ID format'], 400);
                }

                $order = Order::find($localOrderId);

                if ($order) {
                    $rawStatus = $payload['current_status'] ?? 'UNKNOWN';
                    $normalizedStatus = $this->shiprocketService->normalizeStatus($rawStatus);
                    
                    // Update order fields
                    $order->shipping_status = $normalizedStatus;
                    $order->shipping_status_code = (string) ($payload['current_status_id'] ?? $order->shipping_status_code);
                    
                    if (isset($payload['awb']) && !empty($payload['awb'])) {
                        $order->awb_code = (string) $payload['awb'];
                        $order->tracking_url = "https://shiprocket.co/tracking/" . $payload['awb'];
                    }
                    
                    if (isset($payload['courier_name'])) {
                        $order->courier_name = (string) $payload['courier_name'];
                    }

                    if (isset($payload['courier_company_id'])) {
                        $order->courier_company_id = (string) $payload['courier_company_id'];
                    }

                    $order->webhook_payload = $payload;

                    // Map Shiprocket status to local order status
                    $this->syncLocalOrderStatus($order, $normalizedStatus);

                    // Handle dates
                    if (!empty($payload['shipped_date'])) {
                        $order->shipped_at = $payload['shipped_date'];
                    }
                    
                    if (!empty($payload['delivered_date'])) {
                        $order->delivered_at = $payload['delivered_date'];
                    }

                    if (!empty($payload['etd'])) {
                        $order->estimated_delivery_date = $payload['etd'];
                    }

                    $order->save();
                    
                    Log::info('Shiprocket Webhook: Order updated successfully', [
                        'order_id' => $order->id,
                        'raw_status' => $rawStatus,
                        'normalized_status' => $normalizedStatus,
                        'awb' => $order->awb_code
                    ]);

                    $this->triggerNotificationEvents($order, $normalizedStatus);
                } else {
                    Log::warning('Shiprocket Webhook: Order not found in database', ['local_order_id' => $localOrderId]);
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Shiprocket Webhook Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Map normalized shipping status to local order status
     */
    protected function syncLocalOrderStatus(Order $order, $normalizedStatus)
    {
        switch ($normalizedStatus) {
            case \App\Services\ShiprocketService::STATUS_SHIPPED:
            case \App\Services\ShiprocketService::STATUS_IN_TRANSIT:
            case \App\Services\ShiprocketService::STATUS_OUT_FOR_DELIVERY:
                $order->status = 'SHIPPED';
                break;
            case \App\Services\ShiprocketService::STATUS_DELIVERED:
                $order->status = 'DELIVERED';
                break;
            case \App\Services\ShiprocketService::STATUS_CANCELLED:
            case \App\Services\ShiprocketService::STATUS_RTO:
                $order->status = 'CANCELLED';
                break;
        }
    }

    /**
     * Placeholder for Firebase notification events
     */
    protected function triggerNotificationEvents(Order $order, $status)
    {
        $status = strtoupper($status);
        
        // You can dispatch jobs or events here for Firebase Cloud Messaging
        // event(new \App\Events\ShippingStatusUpdated($order, $status));
    }
}
