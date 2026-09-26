<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // پاکسازی کش برای اطمینان از اعمال تغییرات
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('rbac.guard', 'web');
        $allPermissions = Permission::where('guard_name', $guard)->get();

        // 1. نقش Super Admin (دسترسی به همه چیز)
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions($allPermissions);

        // 2. نقش Admin (دسترسی به همه چیز جز مدیریت کاربران و بخش Auth)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $adminPermissions = $allPermissions->filter(function ($permission) {
            return !str_starts_with($permission->name, 'auth.') &&
                !str_starts_with($permission->name, 'users.');
        });
        $admin->syncPermissions($adminPermissions);

        // 3. نقش Manager (دسترسی کامل به عملیات ماژول‌ها، بدون حذف و بخش مالی حساس)
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => $guard]);
        $managerPermissions = $allPermissions->filter(function ($permission) {
            $isDangerous = str_contains($permission->name, 'delete') ||
                str_contains($permission->name, 'approve') ||
                str_starts_with($permission->name, 'auth.');
            return !$isDangerous;
        });
        $manager->syncPermissions($managerPermissions);

        // 4. نقش Viewer (فقط مشاهده)
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => $guard]);
        $viewerPermissions = $allPermissions->filter(function ($permission) {
            return str_contains($permission->name, '.read') ||
                str_contains($permission->name, '.view');
        });
        $viewer->syncPermissions($viewerPermissions);

        // ✅ 5. نقش Employee (پرسنل عادی: فقط داشبورد و مشاهده فیش حقوقی)
        $employee = Role::firstOrCreate(['name' => 'پرسنل', 'guard_name' => $guard]);
        $employeePermissions = $allPermissions->filter(function ($permission) {
            return $permission->name === 'dashboard.view' ||
                $permission->name === 'Payroll.view';
        });
        $employee->syncPermissions($employeePermissions);

        $this->command->info('✅ نقش‌ها و دسترسی‌ها با موفقیت به‌روزرسانی شدند.');
    }
}
