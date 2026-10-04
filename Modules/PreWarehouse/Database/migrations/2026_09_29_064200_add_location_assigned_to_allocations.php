<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // اضافه کردن 'location_assigned' به ENUM جدول allocations
        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'partially_received',
            'fully_received',
            'rejected',
            'rejected_by_destination',
            'location_assigned'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        // بازگرداندن به حالت قبلی
        DB::statement("ALTER TABLE pre_warehouse_allocations MODIFY COLUMN status ENUM(
            'pending',
            'partially_received',
            'fully_received',
            'rejected',
            'rejected_by_destination'
        ) DEFAULT 'pending'");
    }
};
