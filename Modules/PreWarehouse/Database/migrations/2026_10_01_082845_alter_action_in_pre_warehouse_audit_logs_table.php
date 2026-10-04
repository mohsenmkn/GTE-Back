<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pre_warehouse_audit_logs
            MODIFY action VARCHAR(100)
            COLLATE utf8mb4_unicode_ci
            NULL
            DEFAULT 'created'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pre_warehouse_audit_logs
            MODIFY action ENUM(
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
            )
            COLLATE utf8mb4_unicode_ci
            NULL
            DEFAULT 'created'
        ");
    }
};
