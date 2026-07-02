<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\Member;

// Check if TEST123 exists
$member = Member::where('member_id', 'TEST123')->first();

if ($member) {
    echo "TEST123 found: " . $member->full_name . "\n";
} else {
    echo "TEST123 not found, creating...\n";
    
    try {
        $member = Member::create([
            'member_id' => 'TEST123',
            'full_name' => 'Test User One',
            'email' => 'test1@example.com',
            'phone' => '1234567890',
            'password_hash' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'role' => 'MEMBER',
            'sponsor_id' => null,
            'leg' => 'LEFT',
            'placement_path' => '/',
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        echo "TEST123 created successfully!\n";
    } catch (Exception $e) {
        echo "Error creating TEST123: " . $e->getMessage() . "\n";
    }
}
