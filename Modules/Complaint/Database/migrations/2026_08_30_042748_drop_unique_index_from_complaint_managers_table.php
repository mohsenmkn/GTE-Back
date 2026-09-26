<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaint_managers', function (Blueprint $table) {
            // ۱. حذف Foreign Key
            $table->dropForeign(['organizational_unit_id']);

            // ۲. حذف ایندکس یونیک
            $table->dropUnique('unique_active_manager');

            // ۳. اضافه کردن ایندکس یونیک جدید (فقط روی organizational_unit_id)
            $table->unique('organizational_unit_id', 'unique_organizational_unit');

            // . اضافه کردن مجدد Foreign Key
            $table->foreign('organizational_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaint_managers', function (Blueprint $table) {
            // بازگشت به حالت قبل
            $table->dropForeign(['organizational_unit_id']);
            $table->dropUnique('unique_organizational_unit');

            $table->unique(['organizational_unit_id', 'is_active'], 'unique_active_manager');

            $table->foreign('organizational_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->cascadeOnDelete();
        });
    }
};
