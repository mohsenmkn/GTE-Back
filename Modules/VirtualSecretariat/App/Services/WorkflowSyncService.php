<?php


namespace Modules\VirtualSecretariat\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Models\VsWorkflowLog;
use Modules\VirtualSecretariat\App\Repositories\SqlServerAutomationRepository;

class WorkflowSyncService
{
    protected SqlServerAutomationRepository $automationRepo;

    public function __construct(SqlServerAutomationRepository $automationRepo)
    {
        $this->automationRepo = $automationRepo;
    }

    /**
     * همگام‌سازی وضعیت گردش یک درخواست
     */
    public function syncRequestWorkflow(VsRequest $request): void
    {
        if (!$request->automation_entity_code) {
            return;
        }

        // دریافت وضعیت از SQL Server
        $workflowData = $this->automationRepo->getLetterStatus($request->automation_entity_code);

        if (empty($workflowData)) {
            Log::warning("گردش کاری برای EntityCode {$request->automation_entity_code} یافت نشد");
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($workflowData as $item) {
                $this->updateOrCreateWorkflowLog($request, $item);
            }

            // بررسی وضعیت نهایی
            $this->updateRequestFinalStatus($request);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("خطا در همگام‌سازی درخواست {$request->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * به‌روزرسانی یا ایجاد رکورد گردش
     */
    protected function updateOrCreateWorkflowLog(VsRequest $request, object $item): void
    {
        VsWorkflowLog::updateOrCreate(
            [
                'request_id' => $request->id,
                'automation_receiver_code' => $item->Code,
            ],
            [
                'receiver_role_id' => $item->ReceiverRoleID,
                'action_code' => $item->ActionCode,
                'action_name' => $item->ActionName,
                'state' => $this->mapState($item->FinishedOperation),
                'receive_date' => $item->ReceiveDate,
                'response_date' => $item->ResponseDate,
                'response_text' => $item->Description,
            ]
        );
    }

    /**
     * نگاشت وضعیت FinishedOperation به state
     */
    protected function mapState(?int $finishedOperation): string
    {
        return match ($finishedOperation) {
            0 => 'waiting',
            1 => 'finished',
            2 => 'rejected',
            default => 'in_progress',
        };
    }

    /**
     * به‌روزرسانی وضعیت نهایی درخواست
     */
    protected function updateRequestFinalStatus(VsRequest $request): void
    {
        $latestLog = $request->workflowLogs()
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestLog) {
            return;
        }

        $newStatus = match ($latestLog->state) {
            'finished' => 'completed',
            'rejected' => 'rejected',
            'in_progress' => 'processing',
            default => 'sent',
        };

        if ($newStatus !== $request->status) {
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'completed') {
                $updateData['completed_at'] = now();
            }

            $request->update($updateData);
        }
    }

    /**
     * همگام‌سازی تمام درخواست‌های ارسال شده
     */
    public function syncAllPendingRequests(): array
    {
        $requests = VsRequest::where('status', 'sent')
            ->whereNotNull('automation_entity_code')
            ->get();

        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($requests as $request) {
            try {
                $this->syncRequestWorkflow($request);
                $stats['success']++;
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Sync failed for request {$request->id}: {$e->getMessage()}");
            }
        }

        return $stats;
    }
}
