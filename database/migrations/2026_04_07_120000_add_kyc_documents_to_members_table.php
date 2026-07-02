<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // KYC Document Fields
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_image')->nullable();
            
            $table->string('aadhar_number')->nullable();
            $table->string('aadhar_image')->nullable();
            
            $table->string('pan_number')->nullable();
            $table->string('pan_image')->nullable();
            
            // KYC Status
            $table->enum('kyc_status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->text('kyc_rejection_reason')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'bank_account_number',
                'bank_account_image',
                'aadhar_number',
                'aadhar_image',
                'pan_number',
                'pan_image',
                'kyc_status',
                'kyc_rejection_reason',
                'kyc_verified_at'
            ]);
        });
    }
};
