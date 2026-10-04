<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('pre_warehouse_purchases', 'nonconformity_handover_date')) {
                $table->date('nonconformity_handover_date')->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'nonconformity_handover_by')) {
                $table->unsignedBigInteger('nonconformity_handover_by')->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'nonconformity_picked_up_at')) {
                $table->dateTime('nonconformity_picked_up_at')->nullable();
            }
            if (!Schema::hasColumn('pre_warehouse_purchases', 'nonconformity_picked_up_by')) {
                $table->unsignedBigInteger('nonconformity_picked_up_by')->nullable();
            }
        });

        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered','pending_warehouse_approval','approved_by_warehouse','pending_custodian_approval',
            'approved_by_custodian','pending_allocation','allocated','pending_location_assignment','location_assigned',
            'finalized','rejected_by_warehouse','rejected_by_custodian','rejected_by_destination','pending_reallocation',
            'in_quarantine','pending_commercial_voucher','voucher_entered','pending_warehouse_receipt','receipt_entered',
            'fully_received','custodian_approved','partially_received','pending_custodian_final_approval','pending_final_allocation',
            'nonconformity_pending_warehouse_return','nonconformity_pending_commercial_pickup','nonconformity_returned'
        ) DEFAULT 'registered'");
    }

    public function down(): void
    {
        DB::table('pre_warehouse_purchases')->whereIn('status', [
            'nonconformity_pending_warehouse_return','nonconformity_pending_commercial_pickup','nonconformity_returned'
        ])->update(['status' => 'rejected_by_custodian']);
        DB::statement("ALTER TABLE pre_warehouse_purchases MODIFY COLUMN status ENUM(
            'registered','pending_warehouse_approval','approved_by_warehouse','pending_custodian_approval',
            'approved_by_custodian','pending_allocation','allocated','pending_location_assignment','location_assigned',
            'finalized','rejected_by_warehouse','rejected_by_custodian','rejected_by_destination','pending_reallocation',
            'in_quarantine','pending_commercial_voucher','voucher_entered','pending_warehouse_receipt','receipt_entered',
            'fully_received','custodian_approved','partially_received','pending_custodian_final_approval'
        ) DEFAULT 'registered'");
        Schema::table('pre_warehouse_purchases', function (Blueprint $table) {
            $table->dropColumn(['nonconformity_handover_date','nonconformity_handover_by','nonconformity_picked_up_at','nonconformity_picked_up_by']);
        });
    }
};
