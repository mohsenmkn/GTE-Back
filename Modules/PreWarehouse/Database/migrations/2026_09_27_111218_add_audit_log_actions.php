<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // بررسی مقادیر فعلی enum و اضافه کردن مقادیر جدید
        DB::statement("ALTER TABLE pre_warehouse_audit_logs MODIFY COLUMN action ENUM(
            'created',
            'updated',
            'approved',
            'rejected',
            'allocated',
            'location_assigned',
            'finalized',
            'received',
            'voucher_entered',
            'receipt_entered'
        ) DEFAULT 'created'");
    }

    public function down(): void
    {
        // بازگرداندن به حالت قبلی (بدون مقادیر جدید)
        DB::statement("ALTER TABLE pre_warehouse_audit_logs MODIFY COLUMN action ENUM(
            'created',
            'updated',
            'approved',
            'rejected',
            'allocated',
            'location_assigned',
            'finalized',
            'received'
        ) DEFAULT 'created'");
    }
};
