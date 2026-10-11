<?php

namespace Modules\TripExpense\App\Services;

use Carbon\Carbon;
use Modules\TripExpense\App\Models\ExpenseCategory;
use Modules\TripExpense\App\Models\ExpenseLimit;
use Modules\TripExpense\App\Models\Trip;
use Modules\TripExpense\App\Models\TripExpense;

class ExpenseLimitService
{
    /**
     * بررسی سقف هزینه و محاسبه وضعیت
     */
    public function checkLimit(Trip $trip, ExpenseCategory $category, float $newAmount): array
    {
        $expenseDate = Carbon::now()->toDateString();
        $result = [
            'exceeded' => false,
            'warning_message' => null,
            'current_total' => 0,
            'limit_amount' => 0,
            'remaining' => 0,
        ];

        // بررسی سقف سفری
        $perTripLimit = $this->getActiveLimit($category, 'per_trip', $expenseDate);
        if ($perTripLimit) {
            $currentTripTotal = $this->getTripTotal($trip, $category);
            $newTotal = $currentTripTotal + $newAmount;

            if ($newTotal > $perTripLimit->amount) {
                $result['exceeded'] = true;
                $result['warning_message'] = sprintf(
                    '⚠️ هشدار: مجموع هزینه‌های "%s" در این سفر (%s تومان) از سقف مجاز (%s تومان) تجاوز کرده است.',
                    $category->name,
                    number_format($newTotal),
                    number_format($perTripLimit->amount)
                );
                $result['current_total'] = $currentTripTotal;
                $result['limit_amount'] = $perTripLimit->amount;
                $result['remaining'] = $perTripLimit->amount - $currentTripTotal;
            }
        }

        // بررسی سقف ماهانه
        $monthlyLimit = $this->getActiveLimit($category, 'monthly', $expenseDate);
        if ($monthlyLimit) {
            $currentMonthTotal = $this->getMonthlyTotal($trip->driver_id, $category, $expenseDate);
            $newTotal = $currentMonthTotal + $newAmount;

            if ($newTotal > $monthlyLimit->amount) {
                $result['exceeded'] = true;
                $result['warning_message'] = sprintf(
                    '⚠️ هشدار: مجموع هزینه‌های "%s" در این ماه (%s تومان) از سقف مجاز (%s تومان) تجاوز کرده است.',
                    $category->name,
                    number_format($newTotal),
                    number_format($monthlyLimit->amount)
                );
                $result['current_total'] = $currentMonthTotal;
                $result['limit_amount'] = $monthlyLimit->amount;
                $result['remaining'] = $monthlyLimit->amount - $currentMonthTotal;
            }
        }

        return $result;
    }

    /**
     * دریافت سقف فعال برای یک دسته‌بندی
     */
    private function getActiveLimit(ExpenseCategory $category, string $limitType, string $date): ?ExpenseLimit
    {
        return ExpenseLimit::where('category_id', $category->id)
            ->where('limit_type', $limitType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * محاسبه مجموع هزینه‌های یک دسته در یک سفر
     */
    private function getTripTotal(Trip $trip, ExpenseCategory $category): float
    {
        return TripExpense::where('trip_id', $trip->id)
            ->where('category_id', $category->id)
            ->where('status', '!=', 'rejected')
            ->sum('amount');
    }

    /**
     * محاسبه مجموع هزینه‌های یک دسته در یک ماه
     */
    private function getMonthlyTotal(int $driverId, ExpenseCategory $category, string $date): float
    {
        $startOfMonth = Carbon::parse($date)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::parse($date)->endOfMonth()->toDateString();

        return TripExpense::whereHas('trip', function ($query) use ($driverId) {
            $query->where('driver_id', $driverId);
        })
            ->where('category_id', $category->id)
            ->where('status', '!=', 'rejected')
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
    }

    /**
     * محاسبه سقف باقی‌مانده برای یک سفر
     */
    public function getRemainingBudget(Trip $trip, ExpenseCategory $category): array
    {
        $expenseDate = Carbon::now()->toDateString();
        $result = [
            'per_trip' => null,
            'monthly' => null,
        ];

        // سقف سفری
        $perTripLimit = $this->getActiveLimit($category, 'per_trip', $expenseDate);
        if ($perTripLimit) {
            $currentTotal = $this->getTripTotal($trip, $category);
            $result['per_trip'] = [
                'limit' => $perTripLimit->amount,
                'used' => $currentTotal,
                'remaining' => max(0, $perTripLimit->amount - $currentTotal),
            ];
        }

        // سقف ماهانه
        $monthlyLimit = $this->getActiveLimit($category, 'monthly', $expenseDate);
        if ($monthlyLimit) {
            $currentTotal = $this->getMonthlyTotal($trip->driver_id, $category, $expenseDate);
            $result['monthly'] = [
                'limit' => $monthlyLimit->amount,
                'used' => $currentTotal,
                'remaining' => max(0, $monthlyLimit->amount - $currentTotal),
            ];
        }

        return $result;
    }
}
