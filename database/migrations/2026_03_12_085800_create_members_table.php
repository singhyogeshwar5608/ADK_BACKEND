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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique();
            $table->foreignId('sponsor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('leg', ['LEFT', 'RIGHT'])->nullable();
            $table->string('placement_path');
            $table->unsignedInteger('depth');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('role', ['ADMIN', 'MEMBER'])->default('MEMBER');
            $table->string('password_hash');
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'PENDING'])->default('ACTIVE');
            $table->decimal('wallet_balance', 14, 2)->default(0);
            $table->decimal('wallet_total_earned', 14, 2)->default(0);
            $table->decimal('bv_total', 14, 2)->default(0);
            $table->decimal('bv_left_leg', 14, 2)->default(0);
            $table->decimal('bv_right_leg', 14, 2)->default(0);
            $table->decimal('bv_carry_forward_left', 14, 2)->default(0);
            $table->decimal('bv_carry_forward_right', 14, 2)->default(0);
            $table->unsignedInteger('stats_team_size')->default(0);
            $table->unsignedInteger('stats_direct_refs')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('member_id');
            $table->index('placement_path');
            $table->index(['depth', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
