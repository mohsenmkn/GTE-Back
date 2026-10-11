<?php

namespace Modules\TripExpense\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TripExpenseRoleSeeder extends Seeder
{
    public function run(): void
    {
        // ایجاد permission ها
        $permissions = [
            'trip_expense.view',
            'trip_expense.manage',
            'trip_expense.create_trip',
            'trip_expense.view_trips',
            'trip_expense.create_expense',
            'trip_expense.view_expenses',
            'trip_expense.approve_expense',
            'trip_expense.reject_expense',
            'trip_expense.manage_trucks',
            'trip_expense.manage_limits',
            'trip_expense.manage_categories',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ایجاد role راننده
        $driverRole = Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
        $driverRole->syncPermissions([
            'trip_expense.view',
            'trip_expense.create_trip',
            'trip_expense.view_trips',
            'trip_expense.create_expense',
            'trip_expense.view_expenses',
        ]);

        // ایجاد role مدیر ماژول
        $adminRole = Role::firstOrCreate(['name' => 'trip_expense_admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'trip_expense.view',
            'trip_expense.manage',
            'trip_expense.view_trips',
            'trip_expense.view_expenses',
            'trip_expense.approve_expense',
            'trip_expense.reject_expense',
            'trip_expense.manage_trucks',
            'trip_expense.manage_limits',
            'trip_expense.manage_categories',
        ]);

        $this->command->info('✅ TripExpense roles and permissions created successfully!');
    }
}
