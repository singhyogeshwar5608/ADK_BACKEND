<?php

// Direct admin user creation script
echo "Creating Admin User...\n\n";

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    // Check if admin exists
    $admin = DB::table('members')->where('email', 'admin@adk.com')->first();
    
    if ($admin) {
        echo "✅ Admin user already exists!\n";
        echo "Email: admin@adk.com\n";
        echo "Password: admin123\n";
        exit;
    }
    
    // Create admin user
    $adminId = DB::table('members')->insertGetId([
        'member_id' => 'ADMIN001',
        'sponsor_id' => null,
        'leg' => null,
        'placement_path' => '/',
        'depth' => 0,
        'full_name' => 'Admin User',
        'email' => 'admin@adk.com',
        'phone' => '1234567890',
        'address' => null,
        'city' => null,
        'state' => null,
        'profile_image' => null,
        'role' => 'ADMIN',
        'password_hash' => Hash::make('admin123'),
        'status' => 'ACTIVE',
        'is_active' => 1,
        'minimum_bv_required' => 0,
        'wallet_balance' => 0,
        'wallet_total_earned' => 0,
        'weekly_income' => 0,
        'weekly_income_reset_date' => null,
        'bv_total' => 0,
        'bv_left_leg' => 0,
        'bv_right_leg' => 0,
        'bv_carry_forward_left' => 0,
        'bv_carry_forward_right' => 0,
        'total_matched_bv' => 0,
        'first_match_done' => 0,
        'reward_eligible' => 0,
        'level_completion' => 0,
        'stats_team_size' => 0,
        'stats_direct_refs' => 0,
        'direct_referrals_count' => 0,
        'is_repurchase_eligible' => 0,
        'first_purchase_at' => null,
        'last_login_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ SUCCESS: Admin user created!\n\n";
    echo "📋 Admin Details:\n";
    echo "ID: $adminId\n";
    echo "Email: admin@adk.com\n";
    echo "Password: admin123\n";
    echo "Member ID: ADMIN001\n";
    echo "Status: active\n\n";
    
    echo "🎯 You can now login to admin dashboard!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
