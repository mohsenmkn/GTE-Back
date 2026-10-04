<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered',
            'pending_warehouse_approval',
            'approved_by_warehouse',
            'pending_custodian_approval',
            'pending_allocation',
            'allocated',
            'pending_location_assignment',
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

    public function down(): void
    {
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
    }
};
