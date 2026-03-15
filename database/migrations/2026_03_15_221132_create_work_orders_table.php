<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_id')->unique();
            $table->string('line_id');
            $table->string('product_id');
            $table->string('routing_id');
            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->integer('target_qty');
            $table->string('status');
            $table->string('priority');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
