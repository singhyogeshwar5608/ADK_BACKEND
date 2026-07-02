<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Update all existing products to have 10% GST
DB::table('products')->update(['gst_percent' => 10.0]);

echo "Successfully updated all products with 10% GST\n";
