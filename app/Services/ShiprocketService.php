<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ShiprocketService
{
    protected $baseUrl;
    protected $email;
    protected $password;

    // Allowed Statuses
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_SHIPMENT_CREATED = 'SHIPMENT_CREATED';
    const STATUS_AWB_GENERATED = 'AWB_GENERATED';
    const STATUS_AWB_PENDING = 'AWB_PENDING';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_IN_TRANSIT = 'IN_TRANSIT';
    const STATUS_OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_AWB_FAILED = 'AWB_FAILED';
    const STATUS_RTO = 'RTO';

    public function __construct()
    {
        $this->baseUrl = config('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
        $this->email = config('services.shiprocket.email');
        $this->password = config('services.shiprocket.password');
    }

    /**
     * Get Shiprocket Token
     */
    public function getToken()
    {
        return Cache::remember('shiprocket_token', 86400, function () {
            return $this->authenticate();
        });
    }

    /**
     * Authenticate with Shiprocket
     */
    public function authenticate()
    {
        try {
            Log::info('Shiprocket: Authenticating...');
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                Log::info('Shiprocket: Auth Successful');
                return $response->json()['token'];
            }

            Log::error('Shiprocket: Auth Failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            throw new Exception('Shiprocket Authentication Failed: ' . ($response->json()['message'] ?? 'Unknown Error'));
        } catch (Exception $e) {
            Log::error('Shiprocket: Auth Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make a request to Shiprocket API with auto-refresh token
     */
    protected function request($method, $path, $data = [])
    {
        try {
            $token = $this->getToken();
            
            Log::info("Shiprocket API Request: {$method} {$path}", [
                'payload' => $method === 'get' ? [] : $data
            ]);

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->{$method}("{$this->baseUrl}/{$path}", $data);

            if ($response->status() === 401) {
                Log::warning('Shiprocket: Token expired, refreshing...');
                Cache::forget('shiprocket_token');
                $token = $this->getToken();
                
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->{$method}("{$this->baseUrl}/{$path}", $data);
            }

            if (!$response->successful()) {
                Log::warning("Shiprocket API Response Error: {$method} {$path}", [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            } else {
                Log::info("Shiprocket API Response Success: {$method} {$path}");
            }

            return $response;
        } catch (Exception $e) {
            Log::error("Shiprocket API Exception: {$method} {$path}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check Serviceability
     */
    public function checkServiceability($data)
    {
        $response = $this->request('get', 'courier/serviceability/', $data);
        return $response->json();
    }

    /**
     * Create Order in Shiprocket
     */
    public function createOrder($orderData)
    {
        $response = $this->request('post', 'orders/create/adhoc', $orderData);
        return $response->json();
    }

    /**
     * Generate AWB for a shipment
     */
    public function generateAwb($shipmentId, $courierId = null)
    {
        $data = ['shipment_id' => (int) $shipmentId];
        if ($courierId) {
            $data['courier_id'] = (int) $courierId;
        }
        
        $response = $this->request('post', 'courier/assign/awb', $data);
        return $response->json();
    }

    /**
     * Request Pickup
     */
    public function requestPickup($shipmentIds)
    {
        $response = $this->request('post', 'courier/generate/pickup', [
            'shipment_id' => array_map('intval', (array) $shipmentIds)
        ]);
        return $response->json();
    }

    /**
     * Generate Shipping Label
     */
    public function generateLabel($shipmentIds)
    {
        $response = $this->request('post', 'courier/generate/label', [
            'shipment_id' => array_map('intval', (array) $shipmentIds)
        ]);
        return $response->json();
    }

    /**
     * Generate Invoice
     */
    public function generateInvoice($orderIds)
    {
        $response = $this->request('post', 'orders/print/invoice', [
            'ids' => array_map('intval', (array) $orderIds)
        ]);
        return $response->json();
    }

    /**
     * Track Order
     */
    public function trackOrder($shipmentId)
    {
        $response = $this->request('get', "courier/track/shipment/{$shipmentId}");
        return $response->json();
    }

    /**
     * Cancel Order
     */
    public function cancelOrder($orderIds)
    {
        $response = $this->request('post', 'orders/cancel', [
            'ids' => array_map('intval', (array) $orderIds)
        ]);
        return $response->json();
    }

    /**
     * Normalize status from Shiprocket to allowed internal statuses
     */
    public function normalizeStatus($status)
    {
        $status = strtoupper(trim($status));
        
        $map = [
            'NEW' => self::STATUS_PROCESSING,
            'PICKUP SCHEDULED' => self::STATUS_SHIPMENT_CREATED,
            'PICKUP GENERATED' => self::STATUS_SHIPMENT_CREATED,
            'AWB ASSIGNED' => self::STATUS_AWB_GENERATED,
            'SHIPPED' => self::STATUS_SHIPPED,
            'IN TRANSIT' => self::STATUS_IN_TRANSIT,
            'OUT FOR DELIVERY' => self::STATUS_OUT_FOR_DELIVERY,
            'DELIVERED' => self::STATUS_DELIVERED,
            'CANCELLED' => self::STATUS_CANCELLED,
            'RTO' => self::STATUS_RTO,
            'RTO INITIATED' => self::STATUS_RTO,
            'RTO DELIVERED' => self::STATUS_RTO,
            'PICKUP RESCHEDULED' => self::STATUS_SHIPMENT_CREATED,
            'PICKUP ERROR' => self::STATUS_FAILED,
            'LOST' => self::STATUS_FAILED,
            'DAMAGED' => self::STATUS_FAILED,
        ];

        return $map[$status] ?? self::STATUS_PROCESSING;
    }

    /**
     * Transform Order model to Shiprocket payload
     */
    public function transformOrderPayload($order)
    {
        $address = $order->shipping_address ?? [];
        $items = $order->items ?? [];
        
        // Shiprocket requires a valid billing address. Fallback to members table if shipping_address is incomplete.
        $fullName = trim($address['full_name'] ?? $address['name'] ?? ($order->member_snapshot['fullName'] ?? 'Customer'));
        $billingAddress = trim($address['address'] ?? ($order->member_snapshot['address'] ?? 'Address Not Provided'));
        $city = trim($address['city'] ?? ($order->member_snapshot['city'] ?? 'City Not Provided'));
        $pincode = (string) trim($address['zip_code'] ?? $address['pincode'] ?? ($order->member_snapshot['pincode'] ?? '110001'));
        $state = trim($address['state'] ?? ($order->member_snapshot['state'] ?? 'Delhi'));
        $email = trim($address['email'] ?? ($order->member_snapshot['email'] ?? 'customer@example.com'));
        $phone = (string) trim($address['phone'] ?? ($order->member_snapshot['phone'] ?? '9999999999'));

        // Sanitize phone (remove spaces, dashes, ensure 10 digits if possible)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 10) $phone = substr($phone, -10);

        $orderItems = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $orderItems[] = [
                    'name' => substr(trim($item['product_name'] ?? $item['name'] ?? 'General Product'), 0, 50),
                    'sku' => substr(trim($item['sku'] ?? 'SKU-' . ($item['product_id'] ?? $item['productId'] ?? rand(1000, 9999))), 0, 20),
                    'units' => (int) ($item['quantity'] ?? 1),
                    'selling_price' => (float) ($item['price'] ?? 0),
                    'discount' => 0.0,
                    'tax' => 0.0,
                    'hsn' => $item['hsn_code'] ?? 0,
                ];
            }
        }

        // If no items, add a placeholder
        if (empty($orderItems)) {
            $orderItems[] = [
                'name' => 'General Item',
                'sku' => 'GEN-' . $order->id,
                'units' => 1,
                'selling_price' => (float) ($order->total ?? 0),
                'discount' => 0.0,
                'tax' => 0.0,
                'hsn' => 0,
            ];
        }

        // Payment Method Mapping
        $paymentMethod = 'Prepaid';
        $rawMethod = strtoupper($order->payment_method ?? '');
        if ($rawMethod === 'COD') {
            $paymentMethod = 'COD';
        }

        return [
            'order_id' => 'ORD-' . $order->id . '-' . time(), // Use timestamp for uniqueness
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'work'),
            'billing_customer_name' => substr($fullName, 0, 40),
            'billing_last_name' => '',
            'billing_address' => substr($billingAddress, 0, 80),
            'billing_city' => substr($city, 0, 40),
            'billing_pincode' => $pincode,
            'billing_state' => substr($state, 0, 40),
            'billing_country' => 'India',
            'billing_email' => $email,
            'billing_phone' => $phone,
            'shipping_is_billing' => true,
            'order_items' => $orderItems,
            'payment_method' => $paymentMethod,
            'shipping_charges' => 0.0,
            'giftwrap_charges' => 0.0,
            'transaction_charges' => 0.0,
            'total_discount' => (float) ($order->discount ?? 0),
            'sub_total' => (float) ($order->total ?? $order->subtotal ?? 0),
            'length' => 10,
            'breadth' => 10,
            'height' => 10,
            'weight' => 0.5,
        ];
    }
}
