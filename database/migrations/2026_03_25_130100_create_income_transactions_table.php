<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('type', ['SELF', 'SPONSOR', 'MATCHING', 'REWARD', 'REPURCHASE_SELF', 'REPURCHASE_MATCHING', 'REPURCHASE_REWARD']);
            $table->decimal('amount', 14, 2);
            $table->decimal('bv', 14, 2);
            $table->string('source_type')->nullable(); // Order, Product, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('from_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_capped')->default(false);
            $table->timestamps();

            $table->index(['member_id', 'type', 'created_at']);
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_transactions');
    }
};
