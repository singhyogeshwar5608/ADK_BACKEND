<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows guest / retail checkout orders without linking to an MLM member row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->change();
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Do not force non-null member_id if guest rows exist.
    }
};
