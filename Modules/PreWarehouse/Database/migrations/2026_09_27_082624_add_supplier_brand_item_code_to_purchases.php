<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            // تامین‌کننده (اجباری)
            $table->string('supplier', 255)->nullable()->after('target_unit_id');

            // برند محصول (اجباری)
            $table->string('brand', 255)->nullable()->after('supplier');

            // کد کالا (اجباری) - می‌تواند کد تامین‌کننده یا SKU باشد
            $table->string('item_code', 100)->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            $table->dropColumn(['supplier', 'brand', 'item_code']);
        });
    }
};
