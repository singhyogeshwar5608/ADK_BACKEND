<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ShiprocketService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    protected $shiprocket;

    public function __construct(ShiprocketService $shiprocket)
    {
        $this->shiprocket = $shiprocket;
    }

    /**
     * Check courier serviceability
     */
    public function checkServiceability(Request $request)
    {
        $request->validate([
            'pickup_pincode' => 'required',
            'delivery_pincode' => 'required',
            'weight' => 'required',
            'cod' => 'required|boolean',
        ]);

        try {
            $response = $this->shiprocket->checkServiceability($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Serviceability check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Shiprocket order manually if needed
     */
    public function createOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|exists:orders,id']);
        
        $order = Order::findOrFail($request->order_id);
        
        $shiprocketData = $this->shiprocket->transformOrderPayload($order);

        try {
            Log::info('Shiprocket Manual Sync: Creating order', ['order_id' => $order->id]);
            $response = $this->shiprocket->createOrder($shiprocketData);
            
            if (isset($response['order_id'])) {
                $order->update([
                    'shiprocket_order_id' => $response['order_id'],
                    'shipment_id' => $response['shipment_id'],
                    'shipping_status' => ShiprocketService::STATUS_SHIPMENT_CREATED,
                    'shipping_response' => $response
                ]);
                
                Log::info('Shiprocket Manual Sync: Order created', [
                    'order_id' => $order->id,
                    'shiprocket_order_id' => $response['order_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order created in Shiprocket successfully',
                    'data' => $response
                ]);
            }

            Log::warning('Shiprocket Manual Sync: Creation failed', [
                'order_id' => $order->id,
                'response' => $response
            ]);

            $order->update([
                'shipping_status' => ShiprocketService::STATUS_FAILED,
                'shipping_response' => $response
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Shiprocket order: ' . ($response['message'] ?? 'Unknown error'),
                'data' => $response
            ], 400);
        } catch (\Exception $e) {
            Log::error('Shiprocket Manual Sync: Exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Shiprocket order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AWB
     */
    public function generateAwb(Request $request)
    {
        $request->validate(['shipment_id' => 'required']);
        
        try {
            Log::info('Shiprocket Manual AWB: Requesting AWB', ['shipment_id' => $request->shipment_id]);
            $response = $this->shiprocket->generateAwb($request->shipment_id, $request->courier_id);
            
            if (isset($response['awb_assign_status']) && $response['awb_assign_status'] == 1) {
                $awbData = $response['response']['data'];
                Order::where('shipment_id', $request->shipment_id)->update([
                    'awb_code' => $awbData['awb_code'],
                    'courier_name' => $awbData['courier_name'],
                    'shipping_status' => ShiprocketService::STATUS_AWB_GENERATED,
                    'tracking_url' => "https://www.shiprocket.in/tracking/{$awbData['awb_code']}"
                ]);

                Log::info('Shiprocket Manual AWB: Success', [
                    'shipment_id' => $request->shipment_id,
                    'awb' => $awbData['awb_code']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'AWB generated successfully',
                    'data' => $response
                ]);
            }

            Log::warning('Shiprocket Manual AWB: Failed', [
                'shipment_id' => $request->shipment_id,
                'response' => $response
            ]);

            Order::where('shipment_id', $request->shipment_id)->update([
                'shipping_status' => ShiprocketService::STATUS_AWB_FAILED
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AWB generation failed: ' . ($response['message'] ?? 'Unknown error'),
                'data' => $response
            ], 400);
        } catch (\Exception $e) {
            Log::error('Shiprocket Manual AWB: Exception', [
                'shipment_id' => $request->shipment_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'AWB generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request Pickup
     */
    public function requestPickup(Request $request)
    {
        $request->validate(['shipment_id' => 'required']);
        
        try {
            $response = $this->shiprocket->requestPickup($request->shipment_id);
            
            return response()->json([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pickup request failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Tracking Info
     */
    public function getTracking($id)
    {
        $order = Order::findOrFail($id);
        
        if (!$order->shipment_id) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment ID not found for this order'
            ], 404);
        }

        try {
            $response = $this->shiprocket->trackOrder($order->shipment_id);
            
            // Format for Flutter timeline
            $timeline = $this->formatTrackingTimeline($response);

            return response()->json([
                'success' => true,
                'data' => [
                    'awb' => $order->awb_code,
                    'courier' => $order->courier_name,
                    'status' => $order->shipping_status,
                    'estimated_delivery' => $order->estimated_delivery_date,
                    'tracking_url' => $order->tracking_url,
                    'timeline' => $timeline
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format tracking response for Flutter UI
     */
    protected function formatTrackingTimeline($response)
    {
        $timeline = [];
        
        // Shiprocket returns tracking data in various formats depending on the API version
        // Usually it is tracking_data -> shipment_track_activities
        $activities = $response['tracking_data']['shipment_track_activities'] 
                   ?? $response['tracking_data']['shipment_track'][0]['shipment_track_activities'] 
                   ?? [];

        if (is_array($activities)) {
            foreach ($activities as $activity) {
                $timeline[] = [
                    'title' => $activity['activity'] ?? 'Update',
                    'completed' => true,
                    'date' => $activity['date'] ?? null,
                    'location' => $activity['location'] ?? 'In Transit',
                    'status' => $activity['sr-status-label'] ?? null
                ];
            }
        }
        
        return $timeline;
    }
}
