<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adk_events', function (Blueprint $table) {
            $table->id();
            $table->string('leader_name');
            $table->date('meeting_date');
            $table->time('meeting_time');
            $table->string('store_name');
            $table->string('address');
            $table->string('state', 80);
            $table->string('city', 120);
            $table->string('leader_mobile', 20);
            $table->string('store_mobile', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['meeting_date', 'state', 'city'], 'adk_events_date_region_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adk_events');
    }
};
