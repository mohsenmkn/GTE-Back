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
        // مرحله 1: ابتدا enum را گسترش می‌دهیم (قدیمی + جدید)
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
            'pending_custodian_final_approval',
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

        // ═══════════════════════════════════════════════════════════════
        // مرحله 2: حالا داده‌های موجود را تبدیل می‌کنیم
        // ═══════════════════════════════════════════════════════════════

        DB::table('pre_warehouse_purchases')->where('status', 'finalized')->update(['status' => 'fully_received']);
        DB::table('pre_warehouse_purchases')->where('status', 'location_assigned')->update(['status' => 'pending_commercial_voucher']);
        DB::table('pre_warehouse_purchases')->where('status', 'approved_by_custodian')->update(['status' => 'pending_allocation']);
        DB::table('pre_warehouse_purchases')->where('status', 'pending_custodian_approval')->update(['status' => 'pending_custodian_final_approval']);

        // ═══════════════════════════════════════════════════════════════
        // مرحله 3: حالا enum نهایی را اعمال می‌کنیم (بدون مقادیر قدیمی)
        // ═══════════════════════════════════════════════════════════════

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

        // ═══════════════════════════════════════════════════════════════
        // مرحله 4: اضافه کردن فیلدهای جدید (با بررسی وجود هر ستون)
        // ═══════════════════════════════════════════════════════════════

        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('pre_warehouse_purchases', 'warehouse_receipt_number')) {
                $table->string('warehouse_receipt_number', 100)->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'voucher_entered_by')) {
                $table->foreignId('voucher_entered_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'receipt_entered_by')) {
                $table->foreignId('receipt_entered_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'voucher_entered_at')) {
                $table->timestamp('voucher_entered_at')->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'receipt_entered_at')) {
                $table->timestamp('receipt_entered_at')->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'fully_received_at')) {
                $table->timestamp('fully_received_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // بازگرداندن داده‌ها
        DB::table('pre_warehouse_purchases')->where('status', 'fully_received')->update(['status' => 'finalized']);
        DB::table('pre_warehouse_purchases')->where('status', 'pending_commercial_voucher')->update(['status' => 'location_assigned']);
        DB::table('pre_warehouse_purchases')->where('status', 'pending_allocation')->update(['status' => 'approved_by_custodian']);
        DB::table('pre_warehouse_purchases')->where('status', 'pending_custodian_final_approval')->update(['status' => 'pending_custodian_approval']);

        // بازگرداندن enum
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

        // حذف فیلدهای جدید (با بررسی وجود)
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('pre_warehouse_purchases', 'warehouse_receipt_number')) {
                $table->dropColumn('warehouse_receipt_number');
            }
            if (Schema::hasColumn('pre_warehouse_purchases', 'voucher_entered_by')) {
                $table->dropColumn('voucher_entered_by');
            }
            if (Schema::hasColumn('pre_warehouse_purchases', 'receipt_entered_by')) {
                $table->dropColumn('receipt_entered_by');
            }
            if (Schema::hasColumn('pre_warehouse_purchases', 'voucher_entered_at')) {
                $table->dropColumn('voucher_entered_at');
            }
            if (Schema::hasColumn('pre_warehouse_purchases', 'receipt_entered_at')) {
                $table->dropColumn('receipt_entered_at');
            }
            if (Schema::hasColumn('pre_warehouse_purchases', 'fully_received_at')) {
                $table->dropColumn('fully_received_at');
            }
        });
    }
};
