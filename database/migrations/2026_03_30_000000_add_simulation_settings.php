<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('mlm_settings')->insert([
            [
                'key' => 'joining_amount',
                'value' => json_encode(['value' => 10000]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'business_volume',
                'value' => json_encode(['value' => 5000]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'self_income_percent',
                'value' => json_encode(['value' => 10]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'direct_income_percent',
                'value' => json_encode(['value' => 20]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'matching_income_percent',
                'value' => json_encode(['value' => 10]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'self_repurchase_income_percent',
                'value' => json_encode(['value' => 10]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'repurchase_matching_income_percent',
                'value' => json_encode(['value' => 10]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'award_income_percent',
                'value' => json_encode(['value' => 20]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'repurchase_amount',
                'value' => json_encode(['value' => 10000]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'repurchase_bv',
                'value' => json_encode(['value' => 2000]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'weekly_capping',
                'value' => json_encode(['value' => 50000]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('mlm_settings')
            ->whereIn('key', [
                'joining_amount',
                'business_volume',
                'self_income_percent',
                'direct_income_percent',
                'matching_income_percent',
                'self_repurchase_income_percent',
                'repurchase_matching_income_percent',
                'award_income_percent',
                'repurchase_amount',
                'repurchase_bv',
                'weekly_capping',
            ])
            ->delete();
    }
};
