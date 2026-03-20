<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->json('member_snapshot');
            $table->json('items');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('total_bv', 14, 2);
            $table->string('coupon_code')->nullable();
            $table->enum('status', ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'])->default('PENDING');
            $table->string('payment_method');
            $table->enum('payment_status', ['PENDING', 'PAID', 'REFUNDED', 'FAILED'])->default('PENDING');
            $table->json('shipping_address');
            $table->json('history')->nullable();
            $table->timestamps();

            $table->index(['status', 'payment_status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
