<?php

namespace Modules\HR\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Services\GtarabarSyncService;

class SyncUserHRDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * تعداد تلاش مجدد در صورت خطا
     */
    public int $tries = 2;

    /**
     * حداکثر زمان اجرا (ثانیه)
     */
    public int $timeout = 60;

    public function __construct(
        public User $user,
        public string $triggerSource = 'login'
    ) {}

    public function handle(GtarabarSyncService $service): void
    {
        $service->syncUser($this->user, $this->triggerSource);
    }

    /**
     * در صورت شکست نهایی
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error("SyncUserHRDataJob failed for user {$this->user->id}: {$exception->getMessage()}");
    }
}
