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
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {

            // تاریخ تعیین‌شده توسط انبار برای تحویل کالا به بازرگانی
            $table->dateTime('warehouse_return_scheduled_at')
                ->nullable()
                ->after('rejected_at');

            // کاربری که تاریخ تحویل را تعیین کرده
            $table->unsignedBigInteger('warehouse_return_scheduled_by')
                ->nullable()
                ->after('warehouse_return_scheduled_at');

            // تاریخ واقعی تحویل کالا به بازرگانی
            $table->dateTime('commercial_received_at')
                ->nullable()
                ->after('warehouse_return_scheduled_by');

            // کاربر بازرگانی که کالا را تحویل گرفته
            $table->unsignedBigInteger('commercial_received_by')
                ->nullable()
                ->after('commercial_received_at');

            // تاریخ برگشت واقعی به تأمین‌کننده
            $table->dateTime('supplier_returned_at')
                ->nullable()
                ->after('commercial_received_by');

            // کاربر بازرگانی که کالا را به تأمین‌کننده برگشت داده
            $table->unsignedBigInteger('supplier_returned_by')
                ->nullable()
                ->after('supplier_returned_at');

            // توضیحات مربوط به برگشت
            $table->text('return_notes')
                ->nullable()
                ->after('supplier_returned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('', function (Blueprint $table) {

        });
    }
};
