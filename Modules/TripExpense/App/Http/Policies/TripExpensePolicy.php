<?php

namespace Modules\TripExpense\App\Policies;

use Modules\Auth\App\Models\User;
use Modules\TripExpense\App\Models\TripExpense;

class TripExpensePolicy
{
    /**
     * تعیین اینکه آیا کاربر می‌تواند هزینه را ببیند
     */
    public function view(User $user, TripExpense $expense): bool
    {
        // مدیر همه را می‌تواند ببیند
        if ($user->hasPermissionTo('trip_expense.manage')) {
            return true;
        }

        // راننده فقط هزینه‌های سفرهای خودش
        if ($user->hasRole('driver')) {
            return $expense->trip->driver_id === $user->id;
        }

        return false;
    }

    /**
     * تعیین اینکه آیا کاربر می‌تواند هزینه را ایجاد کند
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('trip_expense.create_expense');
    }

    /**
     * تعیین اینکه آیا کاربر می‌تواند هزینه را حذف کند
     */
    public function delete(User $user, TripExpense $expense): bool
    {
        // مدیر می‌تواند حذف کند
        if ($user->hasPermissionTo('trip_expense.manage')) {
            return true;
        }

        // راننده فقط هزینه‌های در انتظار تایید خودش
        if ($user->hasRole('driver')) {
            return $expense->trip->driver_id === $user->id
                && $expense->isPending();
        }

        return false;
    }

    /**
     * تعیین اینکه آیا کاربر می‌تواند هزینه را تایید کند
     */
    public function approve(User $user, TripExpense $expense): bool
    {
        return $user->hasPermissionTo('trip_expense.approve_expense');
    }

    /**
     * تعیین اینکه آیا کاربر می‌تواند هزینه را رد کند
     */
    public function reject(User $user, TripExpense $expense): bool
    {
        return $user->hasPermissionTo('trip_expense.reject_expense');
    }
}
