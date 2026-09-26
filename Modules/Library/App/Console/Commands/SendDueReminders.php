<?php

namespace Modules\Library\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Library\App\Models\Reservation;
use Modules\Library\App\Models\Setting;
use Modules\Library\App\Services\Sms\SmsManager;
use Carbon\Carbon;

class SendDueReminders extends Command
{
    protected $signature = 'library:send-due-reminders';
    protected $description = 'ارسال پیامک یادآوری به کاربرانی که سررسید کتابشان نزدیک است';

    private SmsManager $smsManager;

    public function __construct(SmsManager $smsManager)
    {
        parent::__construct();
        $this->smsManager = $smsManager;
    }

    public function handle(): int
    {
        $this->info('بررسی رزروهای نزدیک به سررسید...');

        $reminderDays = (int) Setting::get('reminder_days_before', 3);
        $targetDate = Carbon::today()->addDays($reminderDays);
        $today = Carbon::today();

        // رزروهای تحویل داده شده که سررسیدشان در بازه [today, today + reminderDays] است
        $reservations = Reservation::with(['user', 'bookCopy.book'])
            ->where('status', 'picked_up')
            ->whereBetween('expected_return_date', [$today, $targetDate])
            ->get();

        $this->info("تعداد رزروهای یافت شده: {$reservations->count()}");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($reservations as $reservation) {
            $daysLeft = $today->diffInDays($reservation->expected_return_date);

            $this->line("  → کاربر: {$reservation->user->name} | کتاب: {$reservation->bookCopy->book->title} | روز باقی‌مانده: {$daysLeft}");

            try {
                if ($daysLeft === 0) {
                    // روز سررسید
                    $sent = $this->smsManager->sendDueDayWarning($reservation);
                } else {
                    // یادآوری
                    $sent = $this->smsManager->sendDueReminder($reservation);
                }

                if ($sent) {
                    $sentCount++;
                    $this->info("    ✅ پیامک ارسال شد");
                } else {
                    $this->warn("    ⚠️ قبلاً ارسال شده یا خطا رخ داد");
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("    ❌ خطا: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("نتیجه: {$sentCount} ارسال موفق | {$failedCount} خطا");

        return Command::SUCCESS;
    }
}
