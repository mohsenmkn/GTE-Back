<?php

namespace Modules\HR\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\HR\App\Services\GtarabarSyncService;

class SyncAllHRDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600; // ۱۰ دقیقه

    public function handle(GtarabarSyncService $service): void
    {
        $service->syncAll('scheduler');
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("SyncAllHRDataJob failed: {$exception->getMessage()}");
    }
}
