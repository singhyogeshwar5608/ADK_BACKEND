<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'member_snapshot',
        'items',
        'subtotal',
        'discount',
        'total',
        'total_bv',
        'coupon_code',
        'status',
        'payment_method',
        'payment_status',
        'shipping_address',
        'history',
        'bv_awarded_at',
        'shiprocket_order_id',
        'shipment_id',
        'awb_code',
        'courier_name',
        'courier_company_id',
        'shipping_status',
        'shipping_status_code',
        'tracking_url',
        'pickup_status',
        'pickup_token',
        'label_url',
        'invoice_url',
        'manifest_url',
        'webhook_payload',
        'shipping_response',
        'shipped_at',
        'delivered_at',
        'estimated_delivery_date',
        'rto_status',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'member_snapshot' => 'array',
        'items' => 'array',
        'shipping_address' => 'array',
        'history' => 'array',
        'webhook_payload' => 'array',
        'shipping_response' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'total_bv' => 'decimal:2',
        'bv_awarded_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
