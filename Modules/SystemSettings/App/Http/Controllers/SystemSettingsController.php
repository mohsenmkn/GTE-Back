<?php

namespace Modules\SystemSettings\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\SystemSettings\App\Services\DynamicDatabaseManager;
use Modules\SystemSettings\App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Cache;

class SystemSettingsController extends Controller
{
    public function __construct(private DynamicDatabaseManager $dbManager) {}

    public function index()
    {
        return response()->json(DatabaseConnection::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:system_database_connections,name',
            'host' => 'required',
            'database' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);

        $conn = DatabaseConnection::create($request->all());
        Cache::forget("db_config_{$conn->name}"); // پاک کردن کش

        return response()->json(['message' => 'اتصال با موفقیت ثبت شد.']);
    }

    public function test(Request $request)
    {
        $result = $this->dbManager->testConnection($request->all());
        return response()->json($result);
    }

    // متد purgeConnection برای استفاده در کدهای دیگر پروژه
    public function purge($name) {
        $this->dbManager->applyConnectionConfig($name);
        // حالا می‌توانید از DB::connection($name)->table(...) استفاده کنید
    }
}
