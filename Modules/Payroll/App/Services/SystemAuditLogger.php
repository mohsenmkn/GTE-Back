<?php

namespace Modules\Payroll\App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Payroll\App\Models\PayrollAuditLog;

class SystemAuditLogger
{
    public function record(
        Request $request,
        string $module,
        string $action,
        string $status = 'success',
        ?int $userId = null,
        ?string $subject = null,
        ?int $employeeId = null,
        ?int $yearMonth = null,
    ): void {
        $userId ??= $request->user()?->id;

        try {
            PayrollAuditLog::query()->create([
                'user_id' => $userId,
                'module' => $module,
                'action' => $action,
                'status' => $status,
                'subject' => $subject,
                'target_employee_id' => $employeeId,
                'year_month' => $yearMonth,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::channel('payroll')->error('System audit persistence failed', [
                'module' => $module,
                'action' => $action,
                'status' => $status,
                'user_id' => $userId,
                'ip' => $request->ip(),
                'exception' => $exception->getMessage(),
            ]);
        }

        Log::channel('payroll')->info($module.'.'.$action, [
            'status' => $status,
            'user_id' => $userId,
            'subject' => $subject,
            'ip' => $request->ip(),
        ]);
    }

    public static function maskMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }

        return substr($mobile, 0, 3).'****'.substr($mobile, -4);
    }
}
