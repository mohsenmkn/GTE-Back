<?php

namespace Modules\HR\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Services\GtarabarSyncService;

class SyncUserPositionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 30;

    public function __construct(
        public User $user
    ) {}

    public function handle(GtarabarSyncService $service): void
    {
        $service->syncUserPosition($this->user);
    }
}
