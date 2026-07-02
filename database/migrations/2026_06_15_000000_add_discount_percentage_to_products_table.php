<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'discount_percentage')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('discount_percentage', 5, 2)->default(0)->after('total_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'discount_percentage')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('discount_percentage');
            });
        }
    }
};
