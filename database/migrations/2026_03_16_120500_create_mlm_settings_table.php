<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mlm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        $now = now();

        DB::table('mlm_settings')->insert([
            [
                'key' => 'binary_commission_rate',
                'value' => json_encode(['value' => 0.10]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'pairing_ratio',
                'value' => json_encode(['left' => 1, 'right' => 1]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'max_daily_income',
                'value' => json_encode(['value' => null]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mlm_settings');
    }
};
