<?php

namespace Modules\Warehouse\App\Providers;

use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/migrations');
    }

    public function register(): void
    {
        // ...
    }
}
