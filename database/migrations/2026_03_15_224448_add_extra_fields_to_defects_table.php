<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->string('operator_id')->nullable()->after('qty');
            $table->string('lot_no')->nullable()->after('operator_id');
            $table->text('note')->nullable()->after('lot_no');
        });
    }

    public function down(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn(['operator_id', 'lot_no', 'note']);
        });
    }
};
