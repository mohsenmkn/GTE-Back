<?php

namespace Modules\Library\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Auth\App\Models\User;
use Spatie\Permission\Models\Role;

class AssignEmployeeRoleToAllUsers extends Command
{
    protected $signature = 'users:assign-employee-role';
    protected $description = 'اختصاص Role کارمند به تمام کاربران موجود در سیستم';

    public function handle(): int
    {
        $this->info(' در حال بررسی کاربران...');

        $employeeRole = Role::where('name', 'employee')->first();

        if (!$employeeRole) {
            $this->error('❌ Role "employee" یافت نشد. ابتدا RoleSeeder را اجرا کنید.');
            return self::FAILURE;
        }

        $users = User::all();
        $updatedCount = 0;

        foreach ($users as $user) {
            if (!$user->hasRole('employee')) {
                $user->assignRole($employeeRole);
                $updatedCount++;
                $this->line("✅ Role به کاربر اضافه شد: {$user->name} ({$user->mobile})");
            } else {
                $this->line("️ کاربر قبلاً Role دارد: {$user->name}");
            }
        }

        $this->info("✅ عملیات تکمیل شد. {$updatedCount} کاربر به‌روزرسانی شدند.");

        return self::SUCCESS;
    }
}
