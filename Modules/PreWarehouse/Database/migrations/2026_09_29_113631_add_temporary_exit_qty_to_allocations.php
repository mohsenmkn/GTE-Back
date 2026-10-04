<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_warehouse_allocations', function (Blueprint $table) {
            $table->integer('temporary_exit_qty')->default(0)->after('received_qty')
                ->comment('مقدار خروج موقت');
        });
    }

    public function down(): void
    {
        Schema::table('pre_warehouse_allocations', function (Blueprint $table) {
            $table->dropColumn('temporary_exit_qty');
        });
    }
};
