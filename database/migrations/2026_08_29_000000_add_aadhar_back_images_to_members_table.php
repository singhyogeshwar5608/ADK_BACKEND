<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('aadhar_back_image')->nullable();
            $table->string('nominee_aadhar_back_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'aadhar_back_image',
                'nominee_aadhar_back_image',
            ]);
        });
    }
};
