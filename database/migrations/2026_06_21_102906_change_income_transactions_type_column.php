<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE income_transactions MODIFY COLUMN type VARCHAR(50) DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE income_transactions MODIFY COLUMN type ENUM('SELF', 'SPONSOR', 'MATCHING', 'REWARD', 'REPURCHASE_SELF', 'REPURCHASE_MATCHING', 'REPURCHASE_REWARD', 'SPONSOR_AWARD') DEFAULT NULL");
    }
};
