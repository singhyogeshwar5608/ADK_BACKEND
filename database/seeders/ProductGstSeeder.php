<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductGstSeeder extends Seeder
{
    public function run(): void
    {
        // Update all existing products to have 10% GST
        DB::table('products')->update(['gst_percent' => 10.0]);
        
        echo "Updated all products with 10% GST\n";
    }
}
