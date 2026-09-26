<?php

namespace Modules\HR\App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\ServiceProvider;
use Modules\HR\App\Listeners\SyncUserAfterLogin;
use Modules\HR\App\Services\GtarabarSyncService;
use Modules\HR\Console\Commands\EnrichTrainingDates;
use Modules\HR\Console\Commands\HRSyncStatus;
use Modules\HR\Console\Commands\ImportAllFromGtarabar;
use Modules\HR\Console\Commands\ReclassifyUsersEmployeeType;
use Modules\HR\Console\Commands\SyncAllTraining;
use Modules\HR\Console\Commands\SyncGtarabarData;

class HRServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            SyncUserAfterLogin::class,
        ],
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');

        // ✅ ثبت Event Listener
        $this->app['events']->listen(
            Login::class,
            SyncUserAfterLogin::class
        );

        $configPath = __DIR__ . '/../Config/config.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'hr');
        }

        $routesPath = __DIR__ . '/../Routes/api.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }

    public function register(): void
    {
        $this->app->singleton(GtarabarSyncService::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncGtarabarData::class,
                HRSyncStatus::class,
                ImportAllFromGtarabar::class,  // ✅ جدید
                SyncAllTraining::class,   // ✅ جدید
                EnrichTrainingDates::class,
                ReclassifyUsersEmployeeType::class
            ]);
        }
    }
}
