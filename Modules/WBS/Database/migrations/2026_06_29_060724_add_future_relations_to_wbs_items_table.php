<?php
// Modules/WBS/Database/migrations/2024_01_01_000004_add_future_relations_to_wbs_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wbs_items', function (Blueprint $table) {
            // برای ماژول Inventory در آینده
            $table->foreignId('material_id')->nullable()->after('category')->comment('Future: Link to materials');

            // برای ماژول Equipment در آینده
            $table->foreignId('equipment_id')->nullable()->after('material_id')->comment('Future: Link to equipment');
        });
    }

    public function down(): void
    {
        Schema::table('wbs_items', function (Blueprint $table) {
            $table->dropColumn(['material_id', 'equipment_id']);
        });
    }
};
