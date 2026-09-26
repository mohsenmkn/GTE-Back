<?php


namespace Database\Seeders;

class PermissionSeeder extends BasePermissionSeeder
{
    public function run(): void
    {
        // خواندن تنظیمات از فایل rbac.php
        $guard = config('rbac.guard', 'web');
        $modules = config('rbac.modules', []);

        // صدا کردن متد اصلاح شده در والد
        $this->seedModulePermissions($modules, $guard);

        $this->command->info('دسترسی‌های سیستم با موفقیت و به همراه لیبل فارسی ایجاد/بروزرسانی شدند.');
    }

}
