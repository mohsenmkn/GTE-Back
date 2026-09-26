<?php
// Modules/SystemSettings/Http/Controllers/DatabaseConnectionController.php

namespace Modules\SystemSettings\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\SystemSettings\App\Models\DatabaseConnection;
use Modules\SystemSettings\App\Http\Requests\StoreDatabaseConnectionRequest;
use Modules\SystemSettings\App\Services\DynamicDatabaseManager;

class DatabaseConnectionController extends Controller
{
    public function __construct(
        private DynamicDatabaseManager $dbManager
    ) {}

    /**
     * لیست تمام اتصالات
     */
    public function index(): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $connections = DatabaseConnection::latest()->get([
            'id', 'name', 'title', 'driver', 'host', 'port',
            'database', 'username', 'is_active', 'description',
            'created_at'
            // ❌ 'password_masked' را از اینجا حذف کنید!
        ]);

        return response()->json([
            'success' => true,
            'data'    => $connections,
        ]);
    }

    /**
     * ذخیره اتصال جدید
     */
    public function store(StoreDatabaseConnectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['options'] = array_merge([
            'TrustServerCertificate' => true,
            'Encrypt' => true,
        ], $data['options'] ?? []);

        $connection = DatabaseConnection::create($data);
        $this->dbManager->clearCache($connection->name);

        return response()->json([
            'success' => true,
            'message' => 'اتصال دیتابیس با موفقیت ثبت شد',
            'data'    => $connection,
        ], 201);
    }

    /**
     * نمایش یک اتصال
     */
    public function show(DatabaseConnection $databaseConnection): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        return response()->json([
            'success' => true,
            'data'    => $databaseConnection,
        ]);
    }

    /**
     * ویرایش اتصال
     */
    public function update(StoreDatabaseConnectionRequest $request, DatabaseConnection $databaseConnection): JsonResponse
    {
        $data = $request->validated();

        // اگر پسورد خالی بود (کاربر تغییر نداده)، پسورد قبلی حفظ شود
        if (empty($data['password']) || $data['password'] === '••••••••') {
            unset($data['password']);
        }

        $oldName = $databaseConnection->name;
        $databaseConnection->update($data);

        // اگر نام تغییر کرده، کش قدیم را پاک کن
        if ($oldName !== $databaseConnection->name) {
            $this->dbManager->clearCache($oldName);
        }
        $this->dbManager->clearCache($databaseConnection->name);

        return response()->json([
            'success' => true,
            'message' => 'اتصال با موفقیت بروزرسانی شد',
            'data'    => $databaseConnection,
        ]);
    }

    /**
     * حذف اتصال
     */
    public function destroy(DatabaseConnection $databaseConnection): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $this->dbManager->clearCache($databaseConnection->name);
        $databaseConnection->delete();

        return response()->json([
            'success' => true,
            'message' => 'اتصال حذف شد',
        ]);
    }

    /**
     * تست اتصال (بدون ذخیره)
     */
    public function test(Request $request): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $validated = $request->validate([
            'driver'   => 'required|in:sqlsrv,mysql,pgsql',
            'host'     => 'required|string',
            'port'     => 'nullable|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
            'options'  => 'nullable|array',
        ]);

        $result = $this->dbManager->testConnection($validated);

        return response()->json($result);
    }

    /**
     * تست اتصال ذخیره شده
     */
    public function testSaved(DatabaseConnection $databaseConnection): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $result = $this->dbManager->testConnection([
            'driver'   => $databaseConnection->driver,
            'host'     => $databaseConnection->host,
            'port'     => $databaseConnection->port,
            'database' => $databaseConnection->database,
            'username' => $databaseConnection->username,
            'password' => $databaseConnection->password,
            'options'  => $databaseConnection->options,
        ]);

        return response()->json($result);
    }

    /**
     * تغییر وضعیت فعال/غیرفعال
     */
    public function toggle(DatabaseConnection $databaseConnection): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $databaseConnection->update(['is_active' => !$databaseConnection->is_active]);
        $this->dbManager->clearCache($databaseConnection->name);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت اتصال تغییر کرد',
            'data'    => ['is_active' => $databaseConnection->is_active],
        ]);
    }

    /**
     * پاک کردن کل کش اتصالات
     */
    public function clearCache(): JsonResponse
    {
        Gate::authorize('system_settings.manage_database');

        $this->dbManager->clearAllCache();

        return response()->json([
            'success' => true,
            'message' => 'کش تمام اتصالات پاک شد',
        ]);
    }
}
