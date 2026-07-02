<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedInteger('serial_no')->nullable()->unique()->after('id');
        });

        DB::statement('SET @row_num = 0');
        DB::statement('UPDATE members SET serial_no = (@row_num := @row_num + 1) ORDER BY id ASC');

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedInteger('serial_no')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('serial_no');
        });
    }
};
