<?php

echo "Creating Admin User...\n\n";

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    $email = 'kalasonipat777@gmail.com';
    
    $admin = DB::table('members')->where('email', $email)->first();
    
    if ($admin) {
        echo "✅ Admin user already exists!\n";
        echo "Email: $email\n";
        echo "Password: kala123\n";
        echo "Member ID: {$admin->member_id}\n";
        exit;
    }
    
    // Get max serial_no
    $maxSerial = DB::table('members')->max('serial_no') ?? 0;
    
    $adminId = DB::table('members')->insertGetId([
        'serial_no' => $maxSerial + 1,
        'member_id' => 'ADMIN001',
        'sponsor_id' => null,
        'leg' => null,
        'placement_path' => '/',
        'depth' => 0,
        'full_name' => 'Kala Soni',
        'email' => $email,
        'phone' => '1234567890',
        'address' => null,
        'city' => null,
        'state' => null,
        'profile_image' => null,
        'role' => 'ADMIN',
        'password_hash' => Hash::make('kala123'),
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
    echo "Email: $email\n";
    echo "Password: kala123\n";
    echo "Member ID: ADMIN001\n";
    echo "Role: ADMIN\n";
    echo "Status: ACTIVE\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
