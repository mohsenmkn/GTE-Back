<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // مرحله 1: اضافه کردن pending_custodian_approval به enum
        // ═══════════════════════════════════════════════════════════════

        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered',
            'pending_warehouse_approval',
            'approved_by_warehouse',
            'pending_custodian_approval',
            'pending_allocation',
            'allocated',
            'pending_custodian_final_approval',
            'approved_by_custodian',
            'pending_commercial_voucher',
            'voucher_entered',
            'pending_warehouse_receipt',
            'receipt_entered',
            'fully_received',
            'rejected_by_warehouse',
            'rejected_by_custodian',
            'rejected_by_destination',
            'pending_reallocation'
        ) DEFAULT 'registered'");

        // ══════════════════════════════════════════════════════════════
        // مرحله 2: تبدیل داده‌های موجود
        // ═══════════════════════════════════════════════════════════════

        // pending_custodian_final_approval → pending_custodian_approval
        DB::table('pre_warehouse_purchases')
            ->where('status', 'pending_custodian_final_approval')
            ->update(['status' => 'pending_custodian_approval']);

        // approved_by_custodian (که قبلاً بعد از تخصیص بود) → pending_allocation
        DB::table('pre_warehouse_purchases')
            ->where('status', 'approved_by_custodian')
            ->update(['status' => 'pending_allocation']);
    }

    public function down(): void
    {
        DB::table('pre_warehouse_purchases')
            ->where('status', 'pending_custodian_approval')
            ->update(['status' => 'pending_custodian_final_approval']);

        DB::table('pre_warehouse_purchases')
            ->where('status', 'pending_allocation')
            ->update(['status' => 'approved_by_custodian']);

        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered',
            'pending_warehouse_approval',
            'approved_by_warehouse',
            'pending_allocation',
            'allocated',
            'pending_custodian_final_approval',
            'approved_by_custodian',
            'pending_commercial_voucher',
            'voucher_entered',
            'pending_warehouse_receipt',
            'receipt_entered',
            'fully_received',
            'rejected_by_warehouse',
            'rejected_by_custodian',
            'rejected_by_destination',
            'pending_reallocation'
        ) DEFAULT 'registered'");
    }
};
