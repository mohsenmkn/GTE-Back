<?php


namespace Modules\VirtualSecretariat\Console\Commands;

use Illuminate\Console\Command;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Services\WorkflowSyncService;

class SyncWorkflowStatus extends Command
{
    protected $signature = 'virtual-secretariat:sync-workflow';
    protected $description = 'همگام‌سازی وضعیت گردش کار نامه‌ها از اتوماسیون';

    protected WorkflowSyncService $syncService;

    public function __construct(WorkflowSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle(): int
    {
        $this->info('شروع همگام‌سازی وضعیت گردش کار...');

        // دریافت درخواست‌های ارسال شده
        $requests = VsRequest::where('status', 'sent')
            ->whereNotNull('automation_entity_code')
            ->get();

        $bar = $this->output->createProgressBar(count($requests));
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($requests as $request) {
            try {
                $this->syncService->syncRequestWorkflow($request);
                $successCount++;
            } catch (\Exception $e) {
                $this->error("خطا در همگام‌سازی درخواست {$request->id}: {$e->getMessage()}");
                $failCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ همگام‌سازی پایان یافت.");
        $this->info("موفق: {$successCount} | ناموفق: {$failCount}");

        return Command::SUCCESS;
    }
}
