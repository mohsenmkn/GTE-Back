<?php

namespace Modules\PreWarehouse\App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PreWarehouse\App\Repositories\Contracts\PurchaseRepositoryInterface;
use Modules\PreWarehouse\App\Repositories\PurchaseRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PurchaseRepositoryInterface::class,
            PurchaseRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
