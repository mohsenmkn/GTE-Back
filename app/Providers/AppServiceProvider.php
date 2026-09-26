<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
//        $this->commands([
//            \Modules\Library\App\Console\Commands\SendManualNotification::class,
//            \Modules\Library\App\Console\Commands\SendDueReminders::class,
//            \Modules\Library\App\Console\Commands\ExpirePendingReservations::class,
//        ]);
    }
}
