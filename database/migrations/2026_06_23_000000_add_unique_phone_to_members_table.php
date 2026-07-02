<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('members')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $records = DB::table('members')
                ->where('phone', $dup->phone)
                ->orderBy('id')
                ->get();

            $records->shift();
            foreach ($records as $record) {
                DB::table('members')
                    ->where('id', $record->id)
                    ->update(['phone' => null]);
            }
        }

        Schema::table('members', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
