<?php


namespace Modules\VirtualSecretariat\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\VirtualSecretariat\App\Models\VsTemplate;

class DynamicQueryBuilder
{
    /**
     * ساخت کوئری INSERT داینامیک بر اساس entity_mapping
     */
    public function buildInsertQuery(VsTemplate $template, array $data): array
    {
        $mapping = $template->entity_mapping;
        $tableName = $mapping['table_name'] ?? 'Entity_Dakhli';
        $fields = $mapping['fields'] ?? [];

        $insertData = [];

        foreach ($fields as $logicalField => $physicalColumn) {
            if (isset($data[$logicalField])) {
                $insertData[$physicalColumn] = $data[$logicalField];
            }
        }

        // اضافه کردن فیلدهای سیستمی
        $insertData['CreatorID'] = $template->virtual_user_id;
        $insertData['CreatorRoleID'] = $template->virtual_role_id;
        $insertData['IsActive'] = 1;
        $insertData['IsConfirm'] = 1;
        $insertData['CreationDate'] = now();
        $insertData['Date'] = $data['date'] ?? now();

        return [
            'connection' => $template->db_connection_name,
            'table' => $tableName,
            'data' => $insertData,
        ];
    }

    /**
     * اجرای کوئری INSERT داینامیک
     */
    public function executeInsert(VsTemplate $template, array $data): int
    {
        $query = $this->buildInsertQuery($template, $data);

        $connection = DB::connection($query['connection']);

        return $connection->table($query['table'])->insertGetId($query['data']);
    }

    /**
     * دریافت لیست جداول دیتابیس اتوماسیون (برای فرانت‌اند)
     */
    public function getAvailableTables(string $connection = 'sqlsrv_automation'): array
    {
        try {
            $tables = DB::connection($connection)
                ->select("
                    SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_TYPE = 'BASE TABLE'
                    ORDER BY TABLE_NAME
                ");

            return collect($tables)->pluck('TABLE_NAME')->toArray();
        } catch (\Exception $e) {
            Log::error("خطا در دریافت جداول: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * دریافت ستون‌های یک جدول
     */
    public function getTableColumns(string $tableName, string $connection = 'sqlsrv_automation'): array
    {
        try {
            $columns = DB::connection($connection)
                ->select("
                    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION
                ", [$tableName]);

            return collect($columns)->map(fn($col) => [
                'name' => $col->COLUMN_NAME,
                'type' => $col->DATA_TYPE,
                'nullable' => $col->IS_NULLABLE === 'YES',
            ])->toArray();
        } catch (\Exception $e) {
            Log::error("خطا در دریافت ستون‌ها: {$e->getMessage()}");
            return [];
        }
    }
}
