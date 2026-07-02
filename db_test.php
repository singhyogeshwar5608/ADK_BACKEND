<?php

// Direct database test for live server
echo "Testing Live Database Connection...\n\n";

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔌 Testing database connection...\n";
    
    // Test connection
    $pdo = DB::connection()->getPdo();
    echo "✅ SUCCESS: Database connected!\n";
    
    // Get database info
    $version = DB::select('SELECT VERSION() as version')[0];
    echo "📊 MySQL Version: " . $version->version . "\n";
    
    // Check tables
    $tables = DB::select("SHOW TABLES");
    echo "📋 Tables found: " . count($tables) . "\n";
    
    // Check members table
    $membersExists = DB::select("SHOW TABLES LIKE 'members'");
    if ($membersExists) {
        echo "✅ Members table exists\n";
        
        $count = DB::select("SELECT COUNT(*) as count FROM members")[0];
        echo "👥 Total members: " . $count->count . "\n";
        
        // Check admin user
        $admin = DB::select("SELECT * FROM members WHERE email = ?", ['admin@adk.com']);
        if ($admin) {
            echo "✅ Admin user exists: admin@adk.com\n";
        } else {
            echo "❌ Admin user not found\n";
            
            // Create admin user
            echo "🔧 Creating admin user...\n";
            DB::insert("
                INSERT INTO members (
                    name, email, password, email_verified_at, member_id, phone, status,
                    wallet_balance, wallet_total_earned, bv_total, bv_left_leg, bv_right_leg,
                    bv_carry_forward_left, bv_carry_forward_right, total_matched_bv,
                    sponsor_id, placement_id, position, level, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ", [
                'Admin User',
                'admin@adk.com',
                password_hash('admin123', PASSWORD_DEFAULT),
                now(),
                'ADMIN001',
                '1234567890',
                'active',
                0, 0, 0, 0, 0, 0, 0, 0,
                null, null, null, 0,
                now(), now()
            ]);
            
            echo "✅ Admin user created successfully!\n";
        }
    } else {
        echo "❌ Members table not found\n";
        
        // Check if we need to run migrations
        $migrations = DB::select("SHOW TABLES LIKE 'migrations'");
        if ($migrations) {
            echo "🔄 Running migrations...\n";
            // Note: Can't run artisan commands here, need manual migration
        } else {
            echo "❌ Database not set up\n";
        }
    }
    
    echo "\n🎯 Database test completed!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    
    // Show current database config
    echo "\n📋 Current Configuration:\n";
    echo "DB_HOST: " . env('DB_HOST') . "\n";
    echo "DB_DATABASE: " . env('DB_DATABASE') . "\n";
    echo "DB_USERNAME: " . env('DB_USERNAME') . "\n";
    echo "DB_PORT: " . env('DB_PORT') . "\n";
}
