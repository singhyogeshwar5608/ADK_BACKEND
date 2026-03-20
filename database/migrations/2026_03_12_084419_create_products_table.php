<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('brand')->default('Independent');
            $table->text('description')->nullable();
            $table->decimal('actual_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('bv', 10, 2);
            $table->unsignedInteger('stock');
            $table->json('categories')->nullable();
            $table->json('images')->nullable();
            $table->decimal('rating', 3, 2)->default(4.50);
            $table->unsignedInteger('popularity_score')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
