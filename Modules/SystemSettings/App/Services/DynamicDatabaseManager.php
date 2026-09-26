<?php
// Modules/SystemSettings/Services/DynamicDatabaseManager.php

namespace Modules\SystemSettings\App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\SystemSettings\App\Models\DatabaseConnection;

class DynamicDatabaseManager
{
    private const CACHE_PREFIX = 'db_conn_';
    private const CACHE_TTL = 3600;

    /**
     * تزریق کانفیگ اتصال به لاراول در Runtime
     */
    public function applyConnectionConfig(string $connectionName): bool
    {
        $config = $this->getConnectionFromCache($connectionName);
        if (!$config) return false;

        Config::set("database.connections.{$connectionName}", $config->toLaravelConfig());
        return true;
    }

    /**
     * دریافت اتصال با کش
     */
    public function getConnectionFromCache(string $name): ?DatabaseConnection
    {
        return Cache::remember(
            self::CACHE_PREFIX . $name,
            self::CACHE_TTL,
            fn() => DatabaseConnection::where('name', $name)->where('is_active', true)->first()
        );
    }

    /**
     * تست اتصال (بدون ذخیره در دیتابیس)
     */
    public function testConnection(array $data): array
    {
        $tempName = 'temp_test_' . uniqid();

        $config = [
            'driver'   => $data['driver'] ?? 'sqlsrv',
            'host'     => $data['host'],
            'port'     => $data['port'] ?? '1433',
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'],
            'charset'  => $data['charset'] ?? 'utf8',
            'collation'=> $data['collation'] ?? 'Persian_100_CI_AI_SC',
            'prefix'   => '',
            'TrustServerCertificate' => $data['options']['TrustServerCertificate'] ?? true,
            'Encrypt'  => $data['options']['Encrypt'] ?? true,
            'options'  => [
                \PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30,
                \PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8,
            ],
        ];

        Config::set("database.connections.{$tempName}", $config);

        $startTime = microtime(true);
        try {
            $pdo = DB::connection($tempName)->getPdo();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            // پاکسازی اتصال موقت
            DB::purge($tempName);
            Config::set("database.connections.{$tempName}", null);

            return [
                'success'   => true,
                'message'   => "✅ اتصال برقرار شد ({$latency}ms)",
                'latency'   => $latency,
                'server_info' => $pdo->getAttribute(\PDO::ATTR_SERVER_INFO),
            ];
        } catch (\Exception $e) {
            Log::error("Database Connection Test Failed: " . $e->getMessage());
            DB::purge($tempName);

            return [
                'success' => false,
                'message' => '❌ خطا در اتصال: ' . $this->parseSqlError($e->getMessage()),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * پاک کردن کش یک اتصال (هنگام تغییر)
     */
    public function clearCache(string $name): void
    {
        Cache::forget(self::CACHE_PREFIX . $name);
    }

    /**
     * پاک کردن تمام کش‌ها
     */
    public function clearAllCache(): void
    {
        DatabaseConnection::pluck('name')->each(fn($n) => $this->clearCache($n));
    }

    /**
     * تبدیل خطای SQL به پیام فارسی
     */
    private function parseSqlError(string $message): string
    {
        return match(true) {
            str_contains($message, 'Login failed') => 'نام کاربری یا کلمه عبور اشتباه است',
            str_contains($message, 'Cannot open database') => 'دیتابیس یافت نشد یا دسترسی ندارید',
            str_contains($message, 'network-related') => 'خطای شبکه - سرور در دسترس نیست',
            str_contains($message, 'timeout') => 'زمان انتظار برای اتصال به پایان رسید',
            str_contains($message, 'SSL Provider') => 'خطای SSL - تنظیمات TrustServerCertificate را بررسی کنید',
            default => $message,
        };
    }
}
