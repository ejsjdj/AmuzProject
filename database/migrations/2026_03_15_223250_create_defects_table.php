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
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->string('defect_id')->unique();   // ← 이 줄 꼭 있어야 함
            $table->dateTime('timestamp')->nullable();
            $table->string('shift_id')->nullable();
            $table->string('line_id');
            $table->string('work_order_id');
            $table->string('station_id');
            $table->string('defect_code');
            $table->integer('qty');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
