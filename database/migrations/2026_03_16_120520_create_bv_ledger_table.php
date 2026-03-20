<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bv_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('direction', ['SELF', 'LEFT', 'RIGHT']);
            $table->decimal('amount', 14, 2);
            $table->string('source_type');
            $table->string('source_id');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bv_ledger');
    }
};
