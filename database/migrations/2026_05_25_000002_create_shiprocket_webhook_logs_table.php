<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiprocket_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('awb')->nullable();
            $table->string('event')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('awb');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiprocket_webhook_logs');
    }
};
