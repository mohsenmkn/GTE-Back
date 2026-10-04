<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // مرحله 1: اضافه کردن فیلد is_quarantine به warehouse_locations
        // ═══════════════════════════════════════════════════════════════

        if (!Schema::hasColumn('warehouse_locations', 'is_quarantine')) {
            Schema::table('warehouse_locations', function (Blueprint $table) {
                $table->boolean('is_quarantine')->default(false)->after('is_active');
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // مرحله 2: اضافه کردن quarantine_location_id به warehouses
        // ═══════════════════════════════════════════════════════════════

        if (!Schema::hasColumn('warehouses', 'quarantine_location_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unsignedBigInteger('quarantine_location_id')->nullable()->after('manager_id');
                $table->foreign('quarantine_location_id')
                    ->references('id')
                    ->on('warehouse_locations')
                    ->nullOnDelete();
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // مرحله 3: اضافه کردن وضعیت in_quarantine به allocations
        // ═══════════════════════════════════════════════════════════════

        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'in_quarantine',
            'partially_received',
            'fully_received',
            'rejected',
            'rejected_by_destination',
            'location_assigned'
        ) DEFAULT 'pending'");

        // ══════════════════════════════════════════════════════════════
        // مرحله 4: تبدیل allocation های pending فعلی به in_quarantine
        // ═══════════════════════════════════════════════════════════════

        DB::table('pre_warehouse_allocations')
            ->where('status', 'pending')
            ->update(['status' => 'in_quarantine']);
    }

    public function down(): void
    {
        DB::table('pre_warehouse_allocations')
            ->where('status', 'in_quarantine')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'partially_received',
            'fully_received',
            'rejected',
            'rejected_by_destination',
            'location_assigned'
        ) DEFAULT 'pending'");

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['quarantine_location_id']);
            $table->dropColumn('quarantine_location_id');
        });

        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->dropColumn('is_quarantine');
        });
    }
};
