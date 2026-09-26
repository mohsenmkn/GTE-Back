<?php


namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeeTraining;
use Modules\HR\App\Services\TrainingSyncService;

class SyncAllTraining extends Command
{
    protected $signature = 'training:sync-all
                            {--from=13920101 : تاریخ شروع (فرمت YYYYMMDD شمسی)}
                            {--months=0 : اگر > 0 باشد، به جای --from از N ماه قبل استفاده می‌شود}
                            {--delay=200 : فاصله بین درخواست‌ها (میلی‌ثانیه)}
                            {--user= : فقط sync یک کاربر خاص (ID)}
                            {--fresh : حذف داده‌های قبلی قبل از sync}';

    protected $description = 'Sync دوره‌های آموزشی همه پرسنل از سامانه آموزش (SOAP)';

    public function handle(TrainingSyncService $service): int
    {
        // ── تعیین تاریخ شروع ──
        $fromDate = $this->option('from');

        if ((int)$this->option('months') > 0) {
            $fromDate = \Morilog\Jalali\Jalalian::now()
                ->subMonths((int)$this->option('months'))
                ->format('Ymd');
        }

        $this->info("🚀 شروع sync آموزش پرسنل از تاریخ: {$fromDate}");
        $this->newLine();

        // ── حالت تک کاربر ──
        if ($userId = $this->option('user')) {
            $user = User::findOrFail($userId);
            $count = $service->syncUser($user, false, $fromDate);
            $this->info("✅ {$count} دوره برای {$user->name} sync شد.");
            return self::SUCCESS;
        }

        // ── حالت fresh ──
        if ($this->option('fresh')) {
            if ($this->confirm('⚠️ همه داده‌های آموزش حذف می‌شوند. ادامه می‌دهید؟')) {
                EmployeeTraining::truncate();
                $this->info('🗑️ داده‌های قبلی حذف شد.');
            }
        }

        // ── شمارش کاربران ──
        $total = User::whereNotNull('national_code')
            ->where('national_code', '!=', '')
            ->count();

        $this->info("👥 تعداد کاربران دارای کد ملی: {$total}");
        $this->newLine();

        // ── Progress Bar ──
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('در حال شروع...');
        $bar->start();

        $stats = $service->syncAllFrom(
            $fromDate,
            function (User $user, array $stats) use ($bar) {
                $bar->setMessage("{$user->name} (مجموع: {$stats['records_synced']} دوره)");
                $bar->advance();
            },
            (int)$this->option('delay')
        );

        $bar->finish();
        $this->newLine(2);

        // ── گزارش نهایی ──
        $this->table(
            ['کل کاربران', 'موفق', 'ناموفق', 'مجموع دوره‌های sync شده'],
            [[
                $stats['total'],
                "<fg=green>{$stats['success']}</>",
                "<fg=red>{$stats['failed']}</>",
                "<fg=cyan>{$stats['records_synced']}</>",
            ]]
        );

        $this->newLine();
        $this->info('📊 مجموع رکوردهای جدول employee_trainings: ' . EmployeeTraining::count());
        $this->info('👥 تعداد کاربرانی که دوره دارند: ' . EmployeeTraining::distinct('user_id')->count('user_id'));

        return self::SUCCESS;
    }
}
