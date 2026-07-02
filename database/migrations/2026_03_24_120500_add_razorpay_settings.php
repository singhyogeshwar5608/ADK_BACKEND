<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Razorpay settings to existing mlm_settings table
        $now = now();

        DB::table('mlm_settings')->insert([
            [
                'key' => 'razorpay_key_id',
                'value' => json_encode(['value' => null]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'razorpay_key_secret',
                'value' => json_encode(['value' => null]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('mlm_settings')
            ->whereIn('key', ['razorpay_key_id', 'razorpay_key_secret'])
            ->delete();
    }
};
