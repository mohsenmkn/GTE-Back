<?php

return [
    'name' => 'VirtualSecretariat',
    /*
   |--------------------------------------------------------------------------
   | Virtual Secretariat Configuration
   |--------------------------------------------------------------------------
   */

    // نام کانکشن پیش‌فرض به SQL Server
    'default_connection' => env('AUTOMATION_DB_CONNECTION', 'sqlsrv_automation'),

    // تنظیمات همگام‌سازی
    'sync' => [
        'enabled' => env('WORKFLOW_SYNC_ENABLED', true),
        'interval_minutes' => env('WORKFLOW_SYNC_INTERVAL', 5), // هر چند دقیقه یکبار
    ],

    // کاربران مجازی پیش‌فرض
    'virtual_users' => [
        'secretariat_user_id' => env('VIRTUAL_SECRETARIAT_USER_ID', 429),
        'secretariat_role_id' => env('VIRTUAL_SECRETARIAT_ROLE_ID', 1880),
    ],

    // Action Code های پیش‌فرض
    'default_actions' => [
        'for_action' => 8,           // جهت امضا
        'for_review' => 1005,         // جهت بررسی و اقدامات لازم
        'for_info' => 1004,           // استحضار
    ],
];
