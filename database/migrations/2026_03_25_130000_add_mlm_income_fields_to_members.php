<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Weekly income tracking
            $table->decimal('weekly_income', 14, 2)->default(0)->after('wallet_total_earned');
            $table->date('weekly_income_reset_date')->nullable()->after('weekly_income');
            
            // Activity tracking
            $table->boolean('is_active')->default(false)->after('status');
            $table->decimal('minimum_bv_required', 10, 2)->default(0)->after('is_active');
            $table->integer('direct_referrals_count')->default(0)->after('stats_direct_refs');
            
            // Repurchase tracking
            $table->boolean('is_repurchase_eligible')->default(false)->after('direct_referrals_count');
            $table->timestamp('first_purchase_at')->nullable()->after('is_repurchase_eligible');
            
            // Matching tracking
            $table->decimal('total_matched_bv', 14, 2)->default(0)->after('bv_carry_forward_right');
            $table->boolean('first_match_done')->default(false)->after('total_matched_bv');
            
            // Reward eligibility
            $table->boolean('reward_eligible')->default(false)->after('first_match_done');
            $table->integer('level_completion')->default(0)->after('reward_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_income',
                'weekly_income_reset_date',
                'is_active',
                'minimum_bv_required',
                'direct_referrals_count',
                'is_repurchase_eligible',
                'first_purchase_at',
                'total_matched_bv',
                'first_match_done',
                'reward_eligible',
                'level_completion',
            ]);
        });
    }
};
