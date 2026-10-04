<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'pre_warehouse_custodian_mappings';

        // بررسی وجود ایندکس قدیمی
        $hasOldIndex = DB::select("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND INDEX_NAME = 'pre_wh_custodian_org_user_unique'
        ");

        if (empty($hasOldIndex)) {
            return;
        }

        // حذف ایندکس قدیمی
        DB::statement("
            ALTER TABLE `{$table}`
            DROP INDEX `pre_wh_custodian_org_user_unique`
        ");

        // ایجاد ایندکس جدید با deleted_at
        DB::statement("
            ALTER TABLE `{$table}`
            ADD UNIQUE INDEX `pre_wh_custodian_org_user_deleted_unique`
            (`organizational_unit_id`, `user_id`, `deleted_at`)
        ");
    }

    public function down(): void
    {
        $table = 'pre_warehouse_custodian_mappings';

        // بررسی وجود ایندکس جدید
        $hasNewIndex = DB::select("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND INDEX_NAME = 'pre_wh_custodian_org_user_deleted_unique'
        ");

        if (empty($hasNewIndex)) {
            return;
        }

        // حذف ایندکس جدید
        DB::statement("
            ALTER TABLE `{$table}`
            DROP INDEX `pre_wh_custodian_org_user_deleted_unique`
        ");

        // بازگرداندن ایندکس قبلی
        DB::statement("
            ALTER TABLE `{$table}`
            ADD UNIQUE INDEX `pre_wh_custodian_org_user_unique`
            (`organizational_unit_id`, `user_id`)
        ");
    }
};
