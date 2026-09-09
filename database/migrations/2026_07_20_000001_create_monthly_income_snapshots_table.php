<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_income_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->decimal('direct', 14, 2)->default(0);
            $table->decimal('matching', 14, 2)->default(0);
            $table->decimal('self_purchase', 14, 2)->default(0);
            $table->decimal('self_repurchase', 14, 2)->default(0);
            $table->decimal('sponsor', 14, 2)->default(0);
            $table->decimal('sponsor_award_kit', 14, 2)->default(0);
            $table->decimal('repurchase_matching', 14, 2)->default(0);
            $table->decimal('downline_monthly_sponsor', 14, 2)->default(0);
            $table->decimal('tds', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['member_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_income_snapshots');
    }
};
