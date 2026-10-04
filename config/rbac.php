<?php


/* Read me: First-->add Primision in file rbac.php
            Secend->run php artisan db:seed --class=PermissionSeeder
            & php artisan db:seed --class=Roleseeder
*/
return [
    'guard' => 'web',

    'modules' => [
        'dashboard' => ['view'],
        'auth' => ['manage', 'view_users'],
        'users' => ['read', 'create', 'update', 'delete'],
        'projects' => ['view','read', 'create', 'update', 'delete'],
        'tasks' => ['read', 'create', 'update', 'delete'],
        'contracts' => ['read', 'create', 'update', 'delete'],
        'contractors' => ['read', 'create', 'update', 'delete'],
        'roles' => ['read', 'create', 'update', 'delete'],
        'budget' => ['read', 'create', 'update', 'delete'],
        'pettycash'=>['read', 'create', 'update', 'delete'],
        'document'=>['read', 'create', 'update', 'delete'],
        'warehouse'=>['read', 'create', 'update', 'delete'],
        'wbs'=>['read', 'create', 'update', 'delete'],
        'permissions'=>['read', 'create', 'update', 'delete'],
        'Material'=>['read', 'create', 'update', 'delete'],
        'categories'=>['read', 'create', 'update', 'delete'],
        'transactions'=>['read', 'create', 'delete'],
        'stock'=>['view', 'create', 'update', 'delete'],
        'low-stock'=>['view', 'create', 'update', 'delete'],
        'consumption'=>['view'],
        'AdminPayroll'=>['view'],
        'Payroll'=>['view'],
        'AdminHr'=>['view'],
        'library'=>['view'],
        'librarybooks'=>['view','manage'],
        'libraryreservations'=>['view','manage','create'],
        'librarystatistics'=>['view'],
        'complaints' => ['read', 'create', 'update', 'delete', 'manage', 'reply', 'export', 'review'],
        'complaintcategories' => ['read', 'create', 'update', 'delete'],
        'complaintstatistics' => ['view'],
        'hr' => ['view', 'manage'],
        'virtual_secretariat' => ['view', 'manage', 'create_request', 'view_requests', 'manage_templates'],
        'workflow_dashboard' => [
            'view',           // مشاهده داشبورد
            'view_all',       // مشاهده همه فرآیندها (ادمین)
            'view_department',// مشاهده فرآیندهای واحد
            'export',         // خروجی Excel
        ],
        //شکایات
        'complaintmanagers' => ['read', 'create', 'update', 'delete'],  // ✅ جدید

        //شایستگی
        'assessment' => ['view', 'evaluate', 'manage', 'approve'],

        'system_settings' => ['manage_database', 'manage_general'],

        'groups' => ['read', 'create', 'update', 'delete', 'assign_users'],


        // ✅ ماژول جدید: Warehouse_Gtrabar
        'warehouse_gtrabar' => [
            'view',           // مشاهده کلی ماژول
            'manage',         // مدیریت کامل
            'stock_view',     // مشاهده موجودی انبار
            'equipment_view', // مشاهده تجهیزات
            'equipment_manage', // مدیریت تجهیزات (ایجاد، ویرایش، حذف)
            'part_trace_view', // مشاهده ردیابی قطعات
            'part_trace_manage', // مدیریت نصب/خروج قطعات
        ],


        'finance' => ['view', 'manage'],
        'equipment_costs' => ['view', 'export', 'manage'],


        // ═══════ ماژول پیش‌انبار ═══════
        'pre_warehouse' => [
            'view',                 // مشاهده لیست و جزئیات خریدها
            'view_all_warehouse',                  // ✅ جدید: مشاهده همه خریدها (بدون فیلتر واحد)
            'commercial_create',    // ثبت خرید جدید (واحد بازرگانی)
            'commercial_finalize',  // نهایی‌سازی خرید و آماده‌سازی رسید (واحد بازرگانی)
            'warehouse_allocate',   // تخصیص کالا به انبارهای مختلف (واحد انبار)
            'warehouse_receive',    // تأیید یا رد دریافت فیزیکی کالا (انبار مقصد)
            'custodian_approve',    // تأیید یا رد نهایی توسط متولی کالا
            'location_assign',      // تعیین محل نگهداری دقیق در انبار
            'history_view',         // مشاهده تاریخچه و لاگ تغییرات
            'item_manage',       // ✅ جدید
            'warehouse_manage',  // ✅ جدید
            'warehouse_approve',      // ✅ جدید: تایید/رد انبار کلی
            'warehouse_receipt',       // ✅ جدید: ورود رسید انبار
            'custodian_approve',  // ✅ تغییر نام از custodian_final_approve
            'commercial_voucher',      // ✅ جدید: ورود حواله بازرگانی
            'warehouse_return_schedule',
        ],






    ],
    'labels' => [
        // ماژول‌ها (برای دسته‌بندی در UI)
        'module_names' => [
            'dashboard' => 'داشبورد',
            'auth'      => 'احراز هویت',
            'permissions'=>'دسترسی ها',
            'users'     => 'مدیریت کاربران',
            'projects'  => 'مدیریت پروژه‌ها',
            'tasks'     => 'مدیریت وظایف',
            'contracts' => 'قراردادها',
            'pettycash' => 'تنخواه گردان',
            'warehouse' => 'انبارداری',
            'roles'     => 'نقش‌ها و دسترسی‌ها',
            'contractors' =>'مدیریت پیمانکاران',
            'document'=>' اسناد و مدارک',
            'budget' =>'بودجه',
            'wbs'=>'ساختار شکست',
            'Material'=>'کالاها',
            'categories'=>'دسته بندی کالا',
            'transactions'=>'تراکنش های انبار',
            'stock'=>'گزارش موجودی',
            'low-stock'=>'گزارش موجودی کم',
            'consumption'=>'گزارش مصرف',
            'AdminPayroll'=>'مدیر فیش حقوقی',
            'Payroll'=>'فیش حقوقی',
            'AdminHr'=>'مدیریت کنترل تردد',
            'librarybooks'=>'مدیریت کتاب ها',
            'libraryreservations'=>'مدیریت رزروها',
            'librarystatistics'=>'مشاهده داشبورد کتابخانه',
            'library'=>'مشاهده کتابخانه',
            'complaints' => 'شکایات، انتقادات و پیشنهادات',
            'complaintcategories' => 'دسته‌بندی شکایات',
            'complaintstatistics' => 'داشبورد آماری شکایات',
            'hr'=>'منابع انسانی',
            'virtual_secretariat' => 'دبیرخانه مجازی',
            'workflow_dashboard' => 'داشبورد فرآیندها',
            'complaintmanagers' => 'مسئولین پیگیری شکایات',  // ✅ جدید
            // در بخش labels.module_names:
            'assessment' => 'ارزیابی عملکرد و شایستگی',
            'system_settings_SqlServer' => 'تنظیمات Sql Server',
            'groups' => 'گروه‌های کاربری',
            'warehouse_gtrabar' => 'انبار (Gtrabar)',

            'finance' => 'ماژول مالی',
            'equipment_costs' => 'گزارش هزینه تجهیزات',

            'pre_warehouse' => 'پیش‌انبار',



        ],

        // اکشن‌های عمومی و اختصاصی
        'actions' => [
            'read'       => 'مشاهده',
            'create'     => 'ایجاد',
            'update'     => 'ویرایش',
            'delete'     => 'حذف',
            'view'       => 'نمایش',
            'manage'     => 'مدیریت کل',
            'view_users' => 'مشاهده کاربران',
            'export'     => 'خروجی اکسل',
            'approve'    => 'تایید نهایی',
            'send'       => 'ارسال',
            'reply'      => 'پاسخ به شکایت',
            // در آرایه 'actions' می‌توانید اضافه کنید (اختیاری):
            'create_request' => 'ثبت درخواست',
            'view_requests' => 'مشاهده درخواست‌ها',
            'manage_templates' => 'مدیریت قالب‌ها',
            'view_all' => 'مشاهده همه فرآیندها',
            'view_department' => 'مشاهده فرآیندهای واحد',
            'review' => 'بررسی و پاسخ به شکایات ارجاع‌شده',  // ✅ جدید
            // در بخش labels.actions:
            'evaluate' => 'ثبت ارزیابی',
            'manage_database' => 'مدیریت اتصالات دیتابیس',
            'manage_general'  => 'تنظیمات عمومی سیستم',
            'assign_users' => 'اختصاص کاربران',


            // ✅ اکشن‌های ماژول Warehouse_Gtrabar
            'stock_view' => 'مشاهده موجودی انبار',
            'equipment_view' => 'مشاهده تجهیزات',
            'equipment_manage' => 'مدیریت تجهیزات',
            'part_trace_view' => 'مشاهده ردیابی قطعات',
            'part_trace_manage' => 'مدیریت نصب/خروج قطعات',


            // ═══════ اکشن‌های ماژول پیش‌انبار ═══════
            'commercial_create'   => 'ثبت خرید جدید',
            'commercial_finalize' => 'نهایی‌سازی خرید',
            'warehouse_allocate'  => 'تخصیص انبار',
            'warehouse_receive'   => 'دریافت / رد کالا',
            'custodian_approve'   => 'تأیید / رد متولی',
            'location_assign'     => 'تعیین محل نگهداری',
            'history_view'        => 'مشاهده تاریخچه',
            'item_manage'      => 'مدیریت کالاها',
            'warehouse_manage' => 'مدیریت انبارها',
            'warehouse_approve' => 'تایید/رد انبار کلی',
            'view_all_warehouse' => 'مشاهده همه خریدها',
            'commercial_voucher' => 'ورود حواله بازرگانی',
            'warehouse_receipt' => 'ورود رسید انبار',
            'warehouse_return_schedule' => 'تعیین تاریخ تحویل کالای مرجوعی به بازرگانی',
        ],
    ]

];
