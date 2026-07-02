<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shiprocket_order_id')->nullable()->after('history');
            $table->string('shipment_id')->nullable()->after('shiprocket_order_id');
            $table->string('awb_code')->nullable()->after('shipment_id');
            $table->string('courier_name')->nullable()->after('awb_code');
            $table->string('shipping_status')->nullable()->after('courier_name');
            $table->string('shipping_status_code')->nullable()->after('shipping_status');
            $table->string('tracking_url')->nullable()->after('shipping_status_code');
            $table->string('pickup_status')->nullable()->after('tracking_url');
            $table->string('pickup_token')->nullable()->after('pickup_status');
            $table->string('label_url')->nullable()->after('pickup_token');
            $table->string('invoice_url')->nullable()->after('label_url');
            $table->string('manifest_url')->nullable()->after('invoice_url');
            $table->json('webhook_payload')->nullable()->after('manifest_url');
            $table->json('shipping_response')->nullable()->after('webhook_payload');
            $table->timestamp('shipped_at')->nullable()->after('shipping_response');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('estimated_delivery_date')->nullable()->after('delivered_at');
            $table->string('rto_status')->nullable()->after('estimated_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shiprocket_order_id', 'shipment_id', 'awb_code', 'courier_name',
                'shipping_status', 'shipping_status_code', 'tracking_url',
                'pickup_status', 'pickup_token', 'label_url', 'invoice_url',
                'manifest_url', 'webhook_payload', 'shipping_response',
                'shipped_at', 'delivered_at', 'estimated_delivery_date', 'rto_status'
            ]);
        });
    }
};
