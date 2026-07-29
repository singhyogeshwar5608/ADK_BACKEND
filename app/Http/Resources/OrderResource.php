<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Disable wrapping for this resource.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'orderNumber' => $this->order_number,
            'memberSnapshot' => [
                'memberId' => data_get($this->member_snapshot, 'memberId'),
                'fullName' => data_get($this->member_snapshot, 'fullName'),
                'email' => data_get($this->member_snapshot, 'email'),
                'phone' => data_get($this->member_snapshot, 'phone'),
            ],
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'totalBv' => (float) $this->total_bv,
            'couponCode' => $this->coupon_code,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'shiprocketOrderId' => $this->shiprocket_order_id,
            'shipmentId' => $this->shipment_id,
            'awbCode' => $this->awb_code,
            'courierName' => $this->courier_name,
            'courierCompanyId' => $this->courier_company_id,
            'shippingStatus' => $this->shipping_status,
            'trackingUrl' => $this->tracking_url,
            'tracking_url' => $this->tracking_url, // Add snake_case for direct access
            'labelUrl' => $this->label_url,
            'invoiceUrl' => $this->invoice_url,
            'manifestUrl' => $this->manifest_url,
            'estimatedDeliveryDate' => $this->estimated_delivery_date?->toIso8601String(),
            'shippingAddress' => $this->shipping_address,
            'createdAt' => $this->created_at?->toIso8601String(),
            'history' => collect($this->history ?? [])->map(function ($entry) {
                return [
                    'status' => $entry['status'] ?? null,
                    'note' => $entry['note'] ?? null,
                    'changedBy' => $entry['changedBy'] ?? null,
                    'changedAt' => $entry['changedAt'] ?? null,
                ];
            })->all(),
        ];
    }
}
