<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('line_id');
            $table->string('work_order_id')->nullable();
            $table->string('station_id');
            $table->string('reason_code');
            $table->integer('duration_minutes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_events');
    }
};
