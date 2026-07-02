<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MLMTestDataSeeder extends Seeder
{
    /**
     * Seed the application's database with MLM test data.
     */
    public function run(): void
    {
        // Create root member (admin)
        $root = Member::create([
            'member_id' => 'ADK001',
            'full_name' => 'Root Admin',
            'email' => 'admin@adk.com',
            'phone' => '9999999999',
            'role' => 'ADMIN',
            'password_hash' => Hash::make('password'),
            'status' => 'ACTIVE',
            'placement_path' => 'root',
            'depth' => 0,
            'leg' => null,
            'sponsor_id' => null,
            'wallet_balance' => 0,
            'wallet_total_earned' => 0,
            'bv_total' => 0,
            'bv_left_leg' => 0,
            'bv_right_leg' => 0,
            'bv_carry_forward_left' => 0,
            'bv_carry_forward_right' => 0,
            'is_active' => true,
            'minimum_bv_required' => 0,
            'direct_referrals_count' => 0,
            'weekly_income' => 0,
        ]);

        // Create test members in binary tree structure
        // Level 1 - Direct referrals of root
        $memberA = Member::create([
            'member_id' => 'ADK002',
            'full_name' => 'Member A',
            'email' => 'membera@test.com',
            'phone' => '9999999001',
            'role' => 'MEMBER',
            'password_hash' => Hash::make('password'),
            'status' => 'ACTIVE',
            'placement_path' => 'root.L',
            'depth' => 1,
            'leg' => 'LEFT',
            'sponsor_id' => $root->id,
            'wallet_balance' => 0,
            'wallet_total_earned' => 0,
            'bv_total' => 0,
            'bv_left_leg' => 0,
            'bv_right_leg' => 0,
            'bv_carry_forward_left' => 0,
            'bv_carry_forward_right' => 0,
            'is_active' => false,
            'minimum_bv_required' => 2000,
            'direct_referrals_count' => 0,
            'weekly_income' => 0,
        ]);

        $memberB = Member::create([
            'member_id' => 'ADK003',
            'full_name' => 'Member B',
            'email' => 'memberb@test.com',
            'phone' => '9999999002',
            'role' => 'MEMBER',
            'password_hash' => Hash::make('password'),
            'status' => 'ACTIVE',
            'placement_path' => 'root.R',
            'depth' => 1,
            'leg' => 'RIGHT',
            'sponsor_id' => $root->id,
            'wallet_balance' => 0,
            'wallet_total_earned' => 0,
            'bv_total' => 0,
            'bv_left_leg' => 0,
            'bv_right_leg' => 0,
            'bv_carry_forward_left' => 0,
            'bv_carry_forward_right' => 0,
            'is_active' => false,
            'minimum_bv_required' => 2000,
            'direct_referrals_count' => 0,
            'weekly_income' => 0,
        ]);

        // Update root's direct referrals count
        $root->update(['direct_referrals_count' => 2]);

        // Level 2 - Children of Member A
        $memberC = Member::create([
            'member_id' => 'ADK004',
            'full_name' => 'Member C',
            'email' => 'memberc@test.com',
            'phone' => '9999999003',
            'role' => 'MEMBER',
            'password_hash' => Hash::make('password'),
            'status' => 'ACTIVE',
            'placement_path' => 'root.L.L',
            'depth' => 2,
            'leg' => 'LEFT',
            'sponsor_id' => $memberA->id,
            'wallet_balance' => 0,
            'wallet_total_earned' => 0,
            'bv_total' => 0,
            'bv_left_leg' => 0,
            'bv_right_leg' => 0,
            'bv_carry_forward_left' => 0,
            'bv_carry_forward_right' => 0,
            'is_active' => false,
            'minimum_bv_required' => 2000,
            'direct_referrals_count' => 0,
            'weekly_income' => 0,
        ]);

        $memberD = Member::create([
            'member_id' => 'ADK005',
            'full_name' => 'Member D',
            'email' => 'memberd@test.com',
            'phone' => '9999999004',
            'role' => 'MEMBER',
            'password_hash' => Hash::make('password'),
            'status' => 'ACTIVE',
            'placement_path' => 'root.L.R',
            'depth' => 2,
            'leg' => 'RIGHT',
            'sponsor_id' => $memberA->id,
            'wallet_balance' => 0,
            'wallet_total_earned' => 0,
            'bv_total' => 0,
            'bv_left_leg' => 0,
            'bv_right_leg' => 0,
            'bv_carry_forward_left' => 0,
            'bv_carry_forward_right' => 0,
            'is_active' => false,
            'minimum_bv_required' => 2000,
            'direct_referrals_count' => 0,
            'weekly_income' => 0,
        ]);

        // Update Member A's direct referrals count
        $memberA->update(['direct_referrals_count' => 2]);

        // Create test products with BV
        Product::create([
            'sku' => 'PROD001',
            'name' => 'Premium Health Supplement',
            'brand' => 'ADK Health',
            'description' => 'High quality health supplement with 50000 BV - Premium MLM Product',
            'actual_price' => 75000.00,
            'total_price' => 80000.00,
            'bv' => 50000,
            'stock' => 100,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::create([
            'sku' => 'PROD002',
            'name' => 'Wellness Package',
            'brand' => 'ADK Wellness',
            'description' => 'Complete wellness package with 30000 BV - Standard MLM Product',
            'actual_price' => 35000.00,
            'total_price' => 40000.00,
            'bv' => 30000,
            'stock' => 50,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::create([
            'sku' => 'PROD003',
            'name' => 'Repurchase Pack',
            'brand' => 'ADK Essentials',
            'description' => 'Repurchase product with 20000 BV - For repurchase testing',
            'actual_price' => 18000.00,
            'total_price' => 20000.00,
            'bv' => 20000,
            'stock' => 200,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::create([
            'sku' => 'PROD004',
            'name' => 'Starter Kit',
            'brand' => 'ADK Starter',
            'description' => 'Beginner starter kit with 10000 BV - Entry level product',
            'actual_price' => 9000.00,
            'total_price' => 10000.00,
            'bv' => 10000,
            'stock' => 150,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->command->info('✅ MLM test data seeded successfully!');
        $this->command->info('');
        $this->command->info('Test Members Created:');
        $this->command->info('- Root Admin (ADK001): admin@adk.com');
        $this->command->info('- Member A (ADK002): membera@test.com');
        $this->command->info('- Member B (ADK003): memberb@test.com');
        $this->command->info('- Member C (ADK004): memberc@test.com');
        $this->command->info('- Member D (ADK005): memberd@test.com');
        $this->command->info('');
        $this->command->info('Test Products Created:');
        $this->command->info('- PROD001: Premium Health Supplement (Price: ₹80,000, BV: 50000)');
        $this->command->info('- PROD002: Wellness Package (Price: ₹40,000, BV: 30000)');
        $this->command->info('- PROD003: Repurchase Pack (Price: ₹20,000, BV: 20000)');
        $this->command->info('- PROD004: Starter Kit (Price: ₹10,000, BV: 10000)');
        $this->command->info('');
        $this->command->info('All passwords: password');
    }
}
