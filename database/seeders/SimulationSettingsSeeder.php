<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimulationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        
        // Insert or update simulation settings
        $settings = [
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
        ];

        foreach ($settings as $setting) {
            DB::table('mlm_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'updated_at' => $now,
                ]
            );
        }

        $this->command->info('Simulation settings seeded successfully!');
    }
}
