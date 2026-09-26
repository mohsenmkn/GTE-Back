<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class BasePermissionSeeder extends Seeder
{
    protected string $guard = 'web';

    protected function seedModulePermissions(array $modules, ?string $guard = null): void
    {
        $guardName = $guard ?? $this->guard;

        // پاکسازی کش پرمیشن‌ها
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // گرفتن لیبل‌ها از فایل rbac
        $labels = config('rbac.labels', []);

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissionName = "{$module}.{$action}";

                // تولید نام فارسی: مثلاً "ویرایش کاربران"
                $actionLabel = $labels['actions'][$action] ?? $action;
                $moduleLabel = $labels['module_names'][$module] ?? $module;
                $displayName = "{$actionLabel} {$moduleLabel}";

                // استفاده از updateOrCreate برای بروزرسانی display_name در صورت تغییر
                \Spatie\Permission\Models\Permission::updateOrCreate(
                    [
                        'name' => $permissionName,
                        'guard_name' => $guardName,
                    ],
                    [
                        'display_name' => $displayName
                    ]
                );
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

}
