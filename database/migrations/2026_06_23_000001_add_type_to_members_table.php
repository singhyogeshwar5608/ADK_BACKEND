<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('members', 'type')) {
            Schema::table('members', function (Blueprint $table) {
                $table->enum('type', ['LEADER', 'USER'])->default('USER')->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('members', 'type')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
