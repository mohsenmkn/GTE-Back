<?php

// Modules/Payroll/App/Services/PayrollService.php

namespace Modules\Payroll\App\Services;

use Modules\Payroll\App\Repositories\PayrollRepository;
use Modules\Payroll\App\DTOs\PayslipDTO;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    public function __construct(
        private PayrollRepository $repository
    ) {}

    /**
     * دریافت فیش حقوقی کامل یک کارمند
     */
    public function getPayslip(string $personnelCode, int $yearMonth): ?PayslipDTO
    {
        $employee = $this->repository->findEmployeeByCode($personnelCode);

        if (!$employee) {
            Log::warning('Employee not found', ['personnel_code' => $personnelCode]);
            return null;
        }

        $items = $this->repository->getPayslipItems($employee->EmployeeID, $yearMonth);
        $summary = $this->repository->getPayslipSummary($employee->EmployeeID, $yearMonth);

        if (empty($items)) {
            Log::info('No payslip data found', [
                'personnel_code' => $personnelCode,
                'year_month' => $yearMonth,
            ]);
            return null;
        }

        return new PayslipDTO(
            personnelCode: $employee->PersonnelCode,
            employeeName: $employee->FullName,
            nationalId: $employee->NationalID,
            fatherName: $employee->FatherName,
            yearMonth: $yearMonth,
            yearMonthLabel: $this->formatYearMonthLabel($yearMonth),
            items: $items,
            totalBenefits: (float) ($summary->TotalBenefits ?? 0),
            totalDeductions: (float) ($summary->TotalDeductions ?? 0),
            netPay: (float) ($summary->NetPay ?? 0),
            effectiveDays: (float) ($summary->EffectiveDays ?? 0),
        );
    }

    /**
     * دریافت لیست ماه‌های موجود
     */
    public function getAvailableMonths(string $personnelCode): array
    {
        $employee = $this->repository->findEmployeeByCode($personnelCode);

        if (!$employee) {
            return [];
        }

        $months = $this->repository->getAvailableMonths($employee->EmployeeID);

        return array_map(function ($month) {
            return [
                'value' => $month,
                'label' => $this->formatYearMonthLabel($month),
            ];
        }, $months);
    }

    /**
     * تبدیل فرمت سال‌ماه (مثلاً 140503 → تیر 1405)
     */
    private function formatYearMonthLabel(int $yearMonth): string
    {
        $year = intdiv($yearMonth, 100);
        $month = $yearMonth % 100;

        $monthNames = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        $monthName = $monthNames[$month] ?? 'نامشخص';

        return "{$monthName} {$year}";
    }

    /**
     * دریافت فیش حقوقی برای مدیران (با EmployeeID)
     */
    public function getPayslipByEmployeeId(int $employeeId, int $yearMonth): ?PayslipDTO
    {
        $items = $this->repository->getPayslipItems($employeeId, $yearMonth);
        $summary = $this->repository->getPayslipSummary($employeeId, $yearMonth);

        if (empty($items)) {
            return null;
        }

        return new PayslipDTO(
            personnelCode: $summary->PersonnelCode,
            employeeName: $summary->EmployeeName,
            nationalId: $summary->NationalID,
            fatherName: $summary->FatherName,
            yearMonth: $yearMonth,
            yearMonthLabel: $this->formatYearMonthLabel($yearMonth),
            items: $items,
            totalBenefits: (float) ($summary->TotalBenefits ?? 0),
            totalDeductions: (float) ($summary->TotalDeductions ?? 0),
            netPay: (float) ($summary->NetPay ?? 0),
            effectiveDays: (float) ($summary->EffectiveDays ?? 0),
        );
    }
}
