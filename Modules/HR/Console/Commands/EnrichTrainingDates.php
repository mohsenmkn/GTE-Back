<?php


namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Services\TrainingSyncService;

class EnrichTrainingDates extends Command
{
    protected $signature = 'training:enrich-dates
                            {--user= : فقط یک کاربر (ID)}
                            {--all : همه کاربران (فقط سطح سال)}
                            {--month-level=1 : تعیین دقیق ماه}
                            {--from-year=1392 : سال شروع اسکن}';

    protected $description = 'کشف تاریخ شروع و پایان دوره‌های آموزشی با تکنیک تنگ‌کردن بازه';

    public function handle(TrainingSyncService $service): int
    {
        $fromYear = (int)$this->option('from-year');

        // ── حالت تک کاربر (با دقت ماه) ──
        if ($userId = $this->option('user')) {
            $user = User::findOrFail($userId);
            $this->info("🔍 تکمیل تاریخ دوره‌های {$user->name}...");

            $stats = $service->enrichDatesForUser(
                $user,
                (bool)$this->option('month-level'),
                $fromYear
            );

            $this->table(
                ['سال اسکن شده', 'ماه اسکن شده', 'دوره‌های دارای تاریخ'],
                [[
                    $stats['years_scanned'],
                    $stats['months_scanned'],
                    "<fg=green>{$stats['updated']}</>",
                ]]
            );
            return self::SUCCESS;
        }

        // ── حالت همه کاربران (فقط سطح سال) ──
        if ($this->option('all')) {
            $users = User::whereNotNull('national_code')
                ->where('national_code', '!=', '')
                ->get();

            $bar = $this->output->createProgressBar(count($users));
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->start();

            foreach ($users as $user) {
                $bar->setMessage($user->name);
                $service->enrichDatesForUser($user, false, $fromYear); // فقط سطح سال
                $bar->advance();
                usleep(100000);
            }

            $bar->finish();
            $this->newLine();
            $this->info('✅ اسکن سال‌ها برای همه کاربران تمام شد.');
            return self::SUCCESS;
        }

        $this->error('یکی از گزینه‌های --user یا --all را مشخص کنید.');
        return self::FAILURE;
    }
}
