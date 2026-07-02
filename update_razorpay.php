<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Get command line arguments
$keyId = $argv[1] ?? null;
$keySecret = $argv[2] ?? null;

if (!$keyId || !$keySecret) {
    echo "Usage: php update_razorpay.php <key_id> <key_secret>\n";
    echo "Example: php update_razorpay.php rzp_live_123456 secret_key_here\n";
    exit(1);
}

try {
    // Update Razorpay Key ID
    DB::table('mlm_settings')
        ->where('key', 'razorpay_key_id')
        ->update(['value' => json_encode(['value' => $keyId])]);
    
    // Update Razorpay Key Secret
    DB::table('mlm_settings')
        ->where('key', 'razorpay_key_secret')
        ->update(['value' => json_encode(['value' => $keySecret])]);
    
    echo "✅ Razorpay keys updated successfully!\n";
    echo "Key ID: $keyId\n";
    echo "Key Secret: " . str_repeat('*', strlen($keySecret)) . "\n";
    echo "\nRefresh your admin panel to see the updated values.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
