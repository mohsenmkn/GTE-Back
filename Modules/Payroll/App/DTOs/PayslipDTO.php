<?php

// Modules/Payroll/App/DTOs/PayslipDTO.php

namespace Modules\Payroll\App\DTOs;

class PayslipDTO
{
    public function __construct(
        public readonly string $personnelCode,
        public readonly string $employeeName,
        public readonly string $nationalId,
        public readonly ?string $fatherName,
        public readonly int $yearMonth,
        public readonly string $yearMonthLabel,
        public readonly array $items,
        public readonly float $totalBenefits,
        public readonly float $totalDeductions,
        public readonly float $netPay,
        public readonly float $effectiveDays,
    ) {}

    /**
     * گروه‌بندی آیتم‌ها بر اساس دسته‌بندی
     */
    public function getGroupedItems(): array
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $category = $item->Category;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'title' => $item->ItemTitle,
                'name' => $item->ItemName,
                'amount' => (float) $item->Amount,
                'formatted_amount' => number_format($item->Amount) . ' ریال',
            ];
        }

        // مرتب‌سازی دسته‌ها
        $order = ['کارکرد', 'مزایا', 'کسورات', 'جمع‌ها'];
        $sorted = [];
        foreach ($order as $cat) {
            if (isset($grouped[$cat])) {
                $sorted[$cat] = $grouped[$cat];
            }
        }

        return $sorted;
    }

    /**
     * تبدیل به آرایه (برای JSON)
     */
    public function toArray(): array
    {
        return [
            'personnel_code' => $this->personnelCode,
            'employee_name' => $this->employeeName,
            'national_id' => $this->nationalId,
            'father_name' => $this->fatherName,
            'year_month' => $this->yearMonth,
            'year_month_label' => $this->yearMonthLabel,
            'items' => $this->getGroupedItems(),
            'summary' => [
                'total_benefits' => $this->totalBenefits,
                'total_deductions' => $this->totalDeductions,
                'net_pay' => $this->netPay,
                'effective_days' => $this->effectiveDays,
                'formatted_net_pay' => number_format($this->netPay) . ' ریال',
                'formatted_total_benefits' => number_format($this->totalBenefits) . ' ریال',
                'formatted_total_deductions' => number_format($this->totalDeductions) . ' ریال',
            ],
        ];
    }
}
