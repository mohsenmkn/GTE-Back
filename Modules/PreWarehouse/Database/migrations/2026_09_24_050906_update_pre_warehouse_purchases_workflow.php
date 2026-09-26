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
        // مرحله 1: تبدیل داده‌های موجود به وضعیت‌های جدید
        // ═══════════════════════════════════════════════════════════════

        // registered → registered (بدون تغییر)
        // allocated → pending_allocation (چون هنوز متولی تایید نکرده)
        // partially_received → allocated (چون بخشی دریافت شده)
        // fully_received → pending_location_assignment (آماده تعیین محل)
        // custodian_approved → pending_allocation (متولی تایید کرده، آماده تخصیص)
        // location_assigned → location_assigned (بدون تغییر)
        // finalized → finalized (بدون تغییر)
        // rejected_by_warehouse → rejected_by_warehouse (بدون تغییر)
        // rejected_by_custodian → rejected_by_custodian (بدون تغییر)

        DB::table('pre_warehouse_purchases')->where('status', 'allocated')->update(['status' => 'pending_allocation']);
        DB::table('pre_warehouse_purchases')->where('status', 'partially_received')->update(['status' => 'allocated']);
        DB::table('pre_warehouse_purchases')->where('status', 'fully_received')->update(['status' => 'pending_location_assignment']);
        DB::table('pre_warehouse_purchases')->where('status', 'custodian_approved')->update(['status' => 'pending_allocation']);

        // ═══════════════════════════════════════════════════════════════
        // مرحله 2: تغییر enum status
        // ═══════════════════════════════════════════════════════════════

        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered',
            'pending_warehouse_approval',
            'approved_by_warehouse',
            'pending_custodian_approval',
            'approved_by_custodian',
            'pending_allocation',
            'allocated',
            'pending_location_assignment',
            'location_assigned',
            'finalized',
            'rejected_by_warehouse',
            'rejected_by_custodian',
            'rejected_by_destination',
            'pending_reallocation'
        ) DEFAULT 'registered'");

        // ═══════════════════════════════════════════════════════════════
        // مرحله 3: اضافه کردن فیلد available_for_allocation
        // ═══════════════════════════════════════════════════════════════

        if (!Schema::hasColumn('pre_warehouse_purchases', 'available_for_allocation')) {
            Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
                $table->unsignedInteger('available_for_allocation')->default(0)
                    ->after('total_received_qty');
            });
        }

        // ═══════════════════════════════════════════════════════════════
        // مرحله 4: محاسبه مقدار اولیه available_for_allocation
        // ═══════════════════════════════════════════════════════════════

        DB::table('pre_warehouse_purchases')->get()->each(function ($purchase) {
            DB::table('pre_warehouse_purchases')
                ->where('id', $purchase->id)
                ->update([
                    'available_for_allocation' => $purchase->quantity - $purchase->total_allocated_qty
                ]);
        });

        // ═══════════════════════════════════════════════════════════════
        // مرحله 5: تغییر enum status در جدول allocations
        // ═══════════════════════════════════════════════════════════════

        // تبدیل داده‌های موجود
        DB::table('pre_warehouse_allocations')->where('status', 'pending')->update(['status' => 'pending']);

        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'partially_received',
            'fully_received',
            'rejected',
            'rejected_by_destination'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        // بازگرداندن داده‌ها به وضعیت‌های قدیمی
        DB::table('pre_warehouse_purchases')->where('status', 'pending_allocation')->update(['status' => 'allocated']);
        DB::table('pre_warehouse_purchases')->where('status', 'allocated')->update(['status' => 'partially_received']);
        DB::table('pre_warehouse_purchases')->where('status', 'pending_location_assignment')->update(['status' => 'fully_received']);
        DB::table('pre_warehouse_purchases')->where('status', 'approved_by_custodian')->update(['status' => 'custodian_approved']);

        // بازگرداندن enum
        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered',
            'allocated',
            'partially_received',
            'fully_received',
            'custodian_approved',
            'location_assigned',
            'finalized',
            'rejected_by_warehouse',
            'rejected_by_custodian'
        ) DEFAULT 'registered'");

        // حذف فیلد
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            $table->dropColumn('available_for_allocation');
        });

        // بازگرداندن enum allocations
        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'partially_received',
            'fully_received',
            'rejected'
        ) DEFAULT 'pending'");
    }
};
