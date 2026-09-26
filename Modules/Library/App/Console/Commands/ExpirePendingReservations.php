<?php

namespace Modules\Library\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Library\App\Models\Reservation;
use Modules\Library\App\Models\Setting;
use Carbon\Carbon;

class ExpirePendingReservations extends Command
{
    protected $signature = 'library:expire-pending';
    protected $description = 'منقضی کردن رزروهای تایید نشده قدیمی‌تر از زمان مشخص';

    public function handle(): int
    {
        $this->info('بررسی رزروهای در انتظار تایید...');

        $expiryHours = (int) Setting::get('reservation_expiry_hours', 48);
        $cutoffDate = Carbon::now()->subHours($expiryHours);

        // رزروهای pending که بیشتر از expiryHours گذشته
        $reservations = Reservation::with(['user', 'bookCopy.book'])
            ->where('status', 'pending')
            ->where('reservation_date', '<', $cutoffDate)
            ->get();

        $this->info("تعداد رزروهای منقضی شده: {$reservations->count()}");

        $expiredCount = 0;

        foreach ($reservations as $reservation) {
            $this->line("  → کاربر: {$reservation->user->name} | کتاب: {$reservation->bookCopy->book->title}");

            try {
                // تغییر وضعیت به منقضی شده
                $reservation->update(['status' => 'expired']);

                // آزادسازی نسخه کتاب
                $reservation->bookCopy->update(['status' => 'available']);

                $expiredCount++;
                $this->info("    ✅ منقضی شد و نسخه آزاد شد");
            } catch (\Exception $e) {
                $this->error("     خطا: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("نتیجه: {$expiredCount} رزرو منقضی شد");

        return Command::SUCCESS;
    }
}
