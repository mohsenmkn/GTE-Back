<?php

namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeRelative;
use Modules\HR\App\Models\HRSyncLog;
use Modules\HR\App\Models\OrganizationalUnit;

class HRSyncStatus extends Command
{
    protected $signature = 'gtarabar:status';
    protected $description = 'نمایش وضعیت sync داده‌های منابع انسانی';

    public function handle(): int
    {
        $this->info('📊 وضعیت جداول HR:');
        $this->newLine();

        $this->table(
            ['جدول', 'تعداد رکورد', 'آخرین sync'],
            [
                [
                    'employee_positions',
                    EmployeePosition::count(),
                    EmployeePosition::max('synced_at') ?? '—',
                ],
                [
                    'employee_relatives',
                    EmployeeRelative::count(),
                    EmployeeRelative::max('synced_at') ?? '—',
                ],
                [
                    'organizational_units',
                    OrganizationalUnit::count(),
                    OrganizationalUnit::max('synced_at') ?? '—',
                ],
            ]
        );

        $this->newLine();

        // آمار sync امروز
        $todayLogs = HRSyncLog::today();
        $successCount = (clone $todayLogs)->success()->count();
        $failedCount  = (clone $todayLogs)->failed()->count();

        $this->info("📈 آمار sync امروز:");
        $this->table(
            ['موفق', 'ناموفق', 'مجموع'],
            [[$successCount, $failedCount, $successCount + $failedCount]]
        );

        // آخرین خطاها
        $recentErrors = HRSyncLog::failed()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentErrors->isNotEmpty()) {
            $this->newLine();
            $this->warn('⚠️ آخرین خطاها:');
            foreach ($recentErrors as $error) {
                $this->line("  [{$error->created_at}] User {$error->user_id}: {$error->error_message}");
            }
        }

        // کارمندان با sync_failed
        $failedEmployees = EmployeePosition::where('sync_failed', true)->count();
        if ($failedEmployees > 0) {
            $this->newLine();
            $this->warn("⚠️ {$failedEmployees} کارمند با sync_failed=true وجود دارد.");
        }

        return self::SUCCESS;
    }
}
