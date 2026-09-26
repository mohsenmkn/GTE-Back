<?php

namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\EmployeeRelative;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Services\GtarabarSyncService;

class ImportAllFromGtarabar extends Command
{
    protected $signature = 'gtarabar:import-all
                        {--users : فقط import کاربران}
                        {--units : فقط import واحدهای سازمانی}
                        {--positions : فقط sync سمت‌ها}
                        {--relatives : فقط sync خانواده}
                        {--role=پرسنل : نقش اختصاصی به کاربران}
                        {--limit=0 : محدود کردن تعداد کاربران (برای تست)}
                        {--dry-run : فقط گزارش بدون ذخیره}';

    protected $description = 'Import کامل همه داده‌ها از گستراب (کاربران، واحدها، سمت‌ها، خانواده)';

    public function handle(GtarabarSyncService $service): int
    {
        $startTime = microtime(true);
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️ حالت Dry Run: هیچ داده‌ای ذخیره نمی‌شود.');
            $this->newLine();
        }

        $this->info('🚀 شروع Import کامل از گستراب');
        $this->newLine();

        $hasFilter = $this->option('users')
            || $this->option('units')
            || $this->option('positions')
            || $this->option('relatives');

        // ── مرحله ۱: Import کاربران ──
        if (!$hasFilter || $this->option('users')) {
            $this->importUsers($service, $dryRun);
        }

        // ── مرحله ۲: Import واحدهای سازمانی ──
        if (!$hasFilter || $this->option('units')) {
            $this->importUnits($service, $dryRun);
        }

        // ── مرحله ۳: Sync سمت‌ها ──
        if (!$hasFilter || $this->option('positions')) {
            $this->syncPositions($service, $dryRun);
        }

        // ── مرحله ۴: Sync خانواده ──
        if (!$hasFilter || $this->option('relatives')) {
            $this->syncRelatives($service, $dryRun);
        }

        $this->newLine();
        $this->showFinalReport($startTime);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────
    //  Import کاربران
    // ─────────────────────────────────────────────
    private function importUsers(GtarabarSyncService $service, bool $dryRun): void
    {
        $limit = (int) $this->option('limit');
        $roleName = $this->option('role') ?: 'پرسنل';

        $this->info("👥 مرحله ۱: Import کاربران از گستراب (Status=2)");
        $this->line("   🎭 نقش اختصاصی: <fg=cyan>{$roleName}</>");

        if ($dryRun) {
            $count = $limit > 0 ? $limit : 'همه';
            $this->line("   حالت dry-run: {$count} کاربر با Status=2 بررسی می‌شود.");
            return;
        }

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('در حال شروع...');
        $bar->start();

        $stats = $service->importUsersFromGtarabar(
            $limit,
            $roleName,
            function ($current, $total, $name) use ($bar) {
                $bar->setMessage($name ?? '...');
                $bar->setProgress($current);
            }
        );

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['کل', 'ساخته شد', 'به‌روز شد', 'رد شد', 'نقص نقش'],
            [[
                $stats['total'],
                "<fg=green>{$stats['created']}</>",
                "<fg=blue>{$stats['updated']}</>",
                "<fg=yellow>{$stats['skipped']}</>",
                "<fg=cyan>{$stats['roles_assigned']}</>",
            ]]
        );

        $this->newLine();
    }

    // ─────────────────────────────────────────────
    //  Import واحدها
    // ─────────────────────────────────────────────
    private function importUnits(GtarabarSyncService $service, bool $dryRun): void
    {
        $this->info('🏢 مرحله ۲: Import واحدهای سازمانی');

        if ($dryRun) {
            $this->line('   حالت dry-run: واحدها بررسی می‌شوند.');
            return;
        }

        $stats = $service->importAllUnits();

        $this->table(
            ['کل', 'ساخته شد', 'رد شد'],
            [[
                $stats['total'],
                "<fg=green>{$stats['created']}</>",
                "<fg=yellow>{$stats['skipped']}</>",
            ]]
        );

        $this->newLine();
    }

    // ─────────────────────────────────────────────
    //  Sync سمت‌ها
    // ─────────────────────────────────────────────
    private function syncPositions(GtarabarSyncService $service, bool $dryRun): void
    {
        $this->info('💼 مرحله ۳: Sync سمت و شغل کارکنان');

        if ($dryRun) {
            $count = User::whereNotNull('personnel_code')->count();
            $this->line("   حالت dry-run: {$count} کاربر بررسی می‌شود.");
            return;
        }

        $users = User::whereNotNull('personnel_code')
            ->where('personnel_code', '!=', '')
            ->get();

        $bar = $this->output->createProgressBar(count($users));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('در حال شروع...');
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($users as $user) {
            $bar->setMessage($user->name ?? "User {$user->id}");

            $position = $service->syncUserPosition($user);
            $position ? $success++ : $failed++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['موفق', 'ناموفق'],
            [["<fg=green>{$success}</>", "<fg=red>{$failed}</>"]]
        );

        $this->newLine();
    }

    // ─────────────────────────────────────────────
    //  Sync خانواده
    // ─────────────────────────────────────────────
    private function syncRelatives(GtarabarSyncService $service, bool $dryRun): void
    {
        $this->info('👨‍👩‍👧‍👦 مرحله ۴: Sync اعضای خانواده');

        if ($dryRun) {
            $count = EmployeePosition::count();
            $this->line("   حالت dry-run: خانواده {$count} کارمند بررسی می‌شود.");
            return;
        }

        $users = User::whereNotNull('personnel_code')
            ->where('personnel_code', '!=', '')
            ->get();

        $bar = $this->output->createProgressBar(count($users));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $totalRelatives = 0;

        foreach ($users as $user) {
            $totalRelatives += $service->syncEmployeeRelatives($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->line("   ✅ مجموع اعضای خانواده sync شده: <fg=green>{$totalRelatives}</>");
        $this->newLine();
    }

    // ─────────────────────────────────────────────
    //  گزارش نهایی
    // ─────────────────────────────────────────────
    private function showFinalReport(float $startTime): void
    {
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('📊 گزارش نهایی جداول:');
        $this->table(
            ['جدول', 'تعداد رکورد'],
            [
                ['users', User::count()],
                ['organizational_units', OrganizationalUnit::count()],
                ['employee_positions', EmployeePosition::count()],
                ['employee_relatives', EmployeeRelative::count()],
            ]
        );

        $this->newLine();
        $this->info("⏱️ زمان کل اجرا: {$duration} ثانیه");
    }
}
