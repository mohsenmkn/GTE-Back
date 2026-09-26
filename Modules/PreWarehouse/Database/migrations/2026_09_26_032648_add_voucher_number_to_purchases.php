<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            // شماره حواله/رسید انبار
            $table->string('voucher_number', 100)->nullable()->after('finalized_at');

            // کاربری که نهایی کرده (برای ردیابی)
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete()->after('voucher_number');
        });
    }

    public function down(): void
    {
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            $table->dropColumn(['voucher_number', 'finalized_by']);
        });
    }
};
