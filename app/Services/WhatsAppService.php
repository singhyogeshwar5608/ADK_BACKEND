<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $targetNumber;
    private string $apiBaseUrl;
    private string $phoneNumberId;
    private string $token;

    public function __construct()
    {
        $this->targetNumber = config('whatsapp.target_number', '918307599904');
        $this->apiBaseUrl = config('whatsapp.api_base_url', 'https://graph.facebook.com/v22.0');
        $this->phoneNumberId = config('whatsapp.phone_number_id', '');
        $this->token = config('whatsapp.token', '');
    }

    public function sendOrderNotification(Order $order): bool
    {
        $member = $order->member;
        if (!$member) {
            Log::warning('WhatsAppService: Order has no member, skipping', ['order_id' => $order->id]);
            return false;
        }

        $message = $this->buildOrderMessage($order, $member);
        return $this->sendMessage($message, "order_notification", $order->id);
    }

    public function sendOrderStatusNotification(Order $order, string $oldStatus, string $newStatus): bool
    {
        $member = $order->member;
        if (!$member) {
            return false;
        }

        $message = $this->buildStatusMessage($order, $member, $oldStatus, $newStatus);
        return $this->sendMessage($message, "status_notification", $order->id);
    }

    public function getOrderWhatsAppLink(Order $order): ?string
    {
        $member = $order->member;
        if (!$member) {
            return null;
        }

        $phone = $this->formatPhone($this->targetNumber);
        $message = $this->buildOrderMessage($order, $member);
        $encoded = rawurlencode($message);

        return "https://wa.me/{$phone}?text={$encoded}";
    }

    public function getOrderStatusWhatsAppLink(Order $order, string $oldStatus, string $newStatus): ?string
    {
        $member = $order->member;
        if (!$member) {
            return null;
        }

        $phone = $this->formatPhone($this->targetNumber);
        $message = $this->buildStatusMessage($order, $member, $oldStatus, $newStatus);
        $encoded = rawurlencode($message);

        return "https://wa.me/{$phone}?text={$encoded}";
    }

    private function sendMessage(string $message, string $type, int $orderId): bool
    {
        if (!$this->token || !$this->phoneNumberId) {
            Log::info('WhatsAppService: API not configured, skipping send', [
                'order_id' => $orderId,
                'type' => $type,
                'has_token' => !empty($this->token),
                'has_phone_id' => !empty($this->phoneNumberId),
            ]);
            return false;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post("{$this->apiBaseUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->targetNumber,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                Log::info('WhatsAppService: Message sent successfully', [
                    'order_id' => $orderId,
                    'type' => $type,
                    'to' => $this->targetNumber,
                    'wa_id' => $response->json('messages.0.id'),
                ]);
                return true;
            }

            Log::error('WhatsAppService: Failed to send', [
                'order_id' => $orderId,
                'type' => $type,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService: Exception sending', [
                'order_id' => $orderId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function formatPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) === 10) {
            return '91' . $cleaned;
        }
        if (!str_starts_with($cleaned, '91')) {
            return '91' . $cleaned;
        }
        return $cleaned;
    }

    private function buildOrderMessage(Order $order, Member $member): string
    {
        $items = $order->items ?? [];
        $itemLines = '';
        $index = 1;

        foreach ($items as $item) {
            $name = $item['product_name'] ?? 'Product';
            $qty = $item['quantity'] ?? 1;
            $price = (float) ($item['price'] ?? 0);
            $total = (float) ($item['total'] ?? ($price * $qty));
            $itemLines .= "{$index}. {$name} (Qty: {$qty}, Price: ₹" . number_format($price, 2) . ")\n";
            $index++;
        }

        $shipping = $order->shipping_address ?? [];
        $shippingAddr = '';
        if (!empty($shipping)) {
            $parts = array_filter([
                $shipping['address'] ?? '',
                $shipping['city'] ?? '',
                $shipping['state'] ?? '',
                $shipping['zip_code'] ?? '',
            ]);
            if (!empty($parts)) {
                $shippingAddr = "\n📍 *Shipping Address:* " . implode(', ', $parts);
            }
        }

        $memberId = $member->member_id ?? $member->id;
        $serialNo = $member->serial_no ?? '-';
        $customerPhone = $this->formatPhone($member->phone ?? '');

        return "Asli Desi Kisan Pvt. Ltd,\n\n"
             . "Your Order Number *#{$order->id}* is under process.\n\n"
             . "📦 *Order Details:*\n"
             . $itemLines . "\n"
             . "💰 *Total Amount:* ₹" . number_format((float) $order->total, 2) . "\n"
             . "💳 *Payment ID:* {$order->id}\n"
             . $shippingAddr . "\n\n"
             . "👤 *Customer Details:*\n"
             . "Name: {$member->full_name}\n"
             . "Phone: {$customerPhone}\n"
             . "👤 *Member Info:*\n"
             . "Member ID: {$memberId}\n"
             . "Serial No: {$serialNo}\n"
             . "Type: " . ($member->type === 'LEADER' ? '👑 LEADER' : '👤 USER') . "\n\n"
             . "Please confirm if you wish to receive the same.";
    }

    private function buildStatusMessage(Order $order, Member $member, string $oldStatus, string $newStatus): string
    {
        $statusLabels = [
            'PENDING' => 'Pending',
            'CONFIRMED' => 'Confirmed',
            'PROCESSING' => 'Processing',
            'SHIPPED' => 'Shipped',
            'DELIVERED' => 'Delivered',
            'CANCELLED' => 'Cancelled',
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $customerPhone = $this->formatPhone($member->phone ?? '');

        $message = "📦 *Order Status Update*\n\n"
                 . "Order Number *#{$order->id}*\n"
                 . "Customer: {$member->full_name}\n"
                 . "Phone: {$customerPhone}\n"
                 . "Status Changed: {$oldLabel} → {$newLabel}\n\n";

        if ($order->awb_code) {
            $message .= "AWB Number: {$order->awb_code}\n";
        }
        if ($order->tracking_url) {
            $message .= "Track Your Order: {$order->tracking_url}\n";
        }

        $message .= "\nTotal Amount: ₹" . number_format((float) $order->total, 2);

        return $message;
    }
}
