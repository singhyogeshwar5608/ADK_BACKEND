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
            $table->decimal('sponsor_award_tracked_bv', 14, 2)->default(0)->after('self_purchase_bv');
        });

        DB::table('members')->update([
            'sponsor_award_tracked_bv' => DB::raw('self_purchase_bv'),
        ]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('sponsor_award_tracked_bv');
        });
    }
};
