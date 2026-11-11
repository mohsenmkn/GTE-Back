<?php

namespace Modules\Payroll\App\Services;

use Illuminate\Http\Request;

class PayrollAuditLogger
{
    public function record(Request $request, string $action, ?int $employeeId = null, ?int $yearMonth = null): void
    {
        $this->systemLogger()->record(
            $request,
            'Payroll',
            $action,
            'success',
            $request->user()?->id,
            $employeeId ? 'employee:'.$employeeId : null,
            $employeeId,
            $yearMonth,
        );
    }

    private function systemLogger(): SystemAuditLogger
    {
        return app(SystemAuditLogger::class);
    }
}
