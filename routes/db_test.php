<?php

// Add this to your routes/web.php or api.php for testing

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

Route::get('/db-test', function () {
    try {
        $pdo = DB::connection()->getPdo();
        $version = DB::select('SELECT VERSION() as version')[0];
        
        return response()->json([
            'status' => 'success',
            'database' => env('DB_DATABASE'),
            'host' => env('DB_HOST'),
            'mysql_version' => $version->version,
            'tables_count' => count(DB::select("SHOW TABLES"))
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'config' => [
                'host' => env('DB_HOST'),
                'database' => env('DB_DATABASE'),
                'username' => env('DB_USERNAME')
            ]
        ]);
    }
});

Route::get('/create-admin', function () {
    try {
        $admin = DB::select("SELECT * FROM members WHERE email = ?", ['admin@adk.com']);
        
        if ($admin) {
            return response()->json(['message' => 'Admin already exists']);
        }
        
        DB::insert("
            INSERT INTO members (
                name, email, password, email_verified_at, member_id, phone, status,
                wallet_balance, wallet_total_earned, bv_total, bv_left_leg, bv_right_leg,
                bv_carry_forward_left, bv_carry_forward_right, total_matched_bv,
                sponsor_id, placement_id, position, level, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            'Admin User', 'admin@adk.com', password_hash('admin123', PASSWORD_DEFAULT),
            now(), 'ADMIN001', '1234567890', 'active',
            0, 0, 0, 0, 0, 0, 0, 0,
            null, null, null, 0, now(), now()
        ]);
        
        return response()->json(['message' => 'Admin created successfully']);
        
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
