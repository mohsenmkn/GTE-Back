<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\App\Models\User;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // پاک کردن کش اسپاتی
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ساخت نقش‌ها
        $adminRole = Role::create(['name' => 'admin']);
        $hrRole = Role::create(['name' => 'hr_manager']);
        $employeeRole = Role::create(['name' => 'employee']);

        // ساخت یک کاربر ادمین برای تست
        $adminUser = User::updateOrCreate(
            ['mobile' => '09138468543'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123') // رمز عبور
            ]
        );
        $adminUser->assignRole($adminRole);
    }
}
