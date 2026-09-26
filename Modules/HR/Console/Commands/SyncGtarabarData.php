<?php

namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeRelative;
use Modules\HR\App\Services\GtarabarSyncService;

class SyncGtarabarData extends Command
{
    protected $signature = 'gtarabar:sync
                            {--user= : فقط sync یک کاربر خاص با ID}
                            {--position : فقط sync سمت (بدون خانواده)}
                            {--relatives : فقط sync خانواده (بدون سمت)}
                            {--fresh : حذف همه داده‌های HR قبل از sync}';

    protected $description = 'همگام‌سازی داده‌های منابع انسانی از گستراب';

    public function handle(GtarabarSyncService $service): int
    {
        // حالت fresh
        if ($this->option('fresh')) {
            if (!$this->confirm('⚠️ همه داده‌های HR حذف و از نو sync می‌شوند. ادامه می‌دهید؟')) {
                return self::FAILURE;
            }

            $this->info('🗑️ در حال حذف داده‌های قبلی...');
            EmployeeRelative::truncate();
            EmployeePosition::truncate();
        }

        // حالت تک‌کاربر
        if ($userId = $this->option('user')) {
            return $this->syncSingleUser($service, (int) $userId);
        }

        // حالت sync همه
        return $this->syncAllUsers($service);
    }

    private function syncSingleUser(GtarabarSyncService $service, int $userId): int
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("❌ کاربر با ID {$userId} یافت نشد.");
            return self::FAILURE;
        }

        $this->info("🔄 در حال sync کاربر {$user->name}...");

        // فقط سمت
        if ($this->option('position')) {
            $position = $service->syncUserPosition($user);
            $position
                ? $this->info("✅ سمت: {$position->post_title}")
                : $this->error("❌ خطا در sync سمت");
            return $position ? self::SUCCESS : self::FAILURE;
        }

        // فقط خانواده
        if ($this->option('relatives')) {
            $count = $service->syncEmployeeRelatives($user);
            $this->info("✅ خانواده: {$count} نفر sync شد.");
            return self::SUCCESS;
        }

        // sync کامل
        $position = $service->syncUser($user, 'cli');

        if ($position) {
            $this->info("✅ سمت: {$position->post_title}");
            $this->info("✅ شغل: {$position->job_title}");
            $this->info("✅ واحد: " . ($position->unit?->title ?? '—'));

            $relativesCount = EmployeeRelative::where('user_id', $user->id)->count();
            $this->info("✅ خانواده: {$relativesCount} نفر");

            return self::SUCCESS;
        }

        $this->error("❌ خطا در sync کاربر {$user->name}");
        return self::FAILURE;
    }

    private function syncAllUsers(GtarabarSyncService $service): int
    {
        $this->info('🔄 شروع همگام‌سازی همه کاربران از گستراب...');
        $this->newLine();

        $stats = $service->syncAll('cli');

        $this->table(
            ['Total', 'Success', 'Failed'],
            [[$stats['total'], $stats['success'], $stats['failed']]]
        );

        $this->newLine();
        $this->info('📊 آمار جداول:');
        $this->table(
            ['جدول', 'تعداد رکورد'],
            [
                ['employee_positions', EmployeePosition::count()],
                ['employee_relatives', EmployeeRelative::count()],
                ['organizational_units', \Modules\HR\App\Models\OrganizationalUnit::count()],
            ]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
