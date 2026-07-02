<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('left_bv_matched', 14, 2);
            $table->decimal('right_bv_matched', 14, 2);
            $table->decimal('matched_bv', 14, 2);
            $table->decimal('income_earned', 14, 2);
            $table->decimal('percentage_applied', 5, 2);
            $table->boolean('is_first_match')->default(false);
            $table->string('match_ratio')->nullable(); // 2:1 or 1:1
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_history');
    }
};
