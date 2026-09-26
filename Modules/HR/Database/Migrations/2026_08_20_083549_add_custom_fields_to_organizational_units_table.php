<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            // آیا واحد دستی است (در گستراب نیست)
            $table->boolean('is_custom')->default(false)->after('path');
            // ترتیب نمایش
            $table->unsignedInteger('sort_order')->default(0)->after('is_custom');
            // توضیحات
            $table->text('description')->nullable()->after('sort_order');
        });

        // gt_department_id باید nullable شود (برای واحدهای دستی)
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->unsignedBigInteger('gt_department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropColumn(['is_custom', 'sort_order', 'description']);
            $table->unsignedBigInteger('gt_department_id')->nullable(false)->change();
        });
    }
};
