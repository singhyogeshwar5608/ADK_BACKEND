<?php

// Check members table structure
echo "Checking Members Table Structure...\n\n";

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Get table structure
    $columns = DB::select("DESCRIBE members");
    
    echo "📋 Members Table Columns:\n";
    echo str_repeat("-", 60) . "\n";
    
    foreach ($columns as $column) {
        echo sprintf("%-30s %-20s %s\n", 
            $column->Field, 
            $column->Type,
            $column->Null === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    
    echo "\n✅ Table structure retrieved successfully!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
