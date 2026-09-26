<?php

namespace Modules\Library\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

class LibraryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerRoutes();

    }

    /**
     * ثبت دستورات Artisan ماژول
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Library\App\Console\Commands\SendManualNotification::class,
                \Modules\Library\App\Console\Commands\SendDueReminders::class,
                \Modules\Library\App\Console\Commands\ExpirePendingReservations::class,
            ]);
        }
    }

    /**
     * ثبت زمان‌بندی‌های (Schedule) ماژول
     */
    private function registerSchedule(): void
    {
        // این روش تضمین می‌کند که Schedule به درستی resolve می‌شود
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {

            // هر روز ساعت 8 صبح: یادآوری سررسید
            $schedule->command('library:send-due-reminders')
                ->dailyAt('08:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/library-cron.log'));

            // هر روز ساعت 2 بامداد: منقضی کردن رزروهای قدیمی
            $schedule->command('library:expire-pending')
                ->dailyAt('02:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/library-cron.log'));
        });
    }

    /**
     * 🔑 ثبت روت‌های ماژول
     */
    private function registerRoutes(): void
    {
        $routesPath = base_path('Modules/Library/routes/api.php');

        if (file_exists($routesPath)) {
            Route::prefix('api/v1')  // 🔑 تغییر از 'api' به 'api/v1'
            ->middleware('api')
                ->group($routesPath);
        }
    }
}
