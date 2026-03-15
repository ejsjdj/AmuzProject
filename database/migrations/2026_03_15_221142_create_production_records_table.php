<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_id')->unique();
            $table->dateTime('timestamp');
            $table->string('shift_id')->nullable();
            $table->string('line_id');
            $table->string('work_order_id');
            $table->string('station_id');
            $table->integer('input_qty');
            $table->integer('good_qty');
            $table->integer('scrap_qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};
