<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vs_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام قالب (مثلاً: گواهی اشتغال به کار)
            $table->string('slug')->unique(); // شناسه یکتا
            $table->text('description')->nullable();

            // تنظیمات اتصال
            $table->string('db_connection_name')->default('sqlsrv_automation');

            // نگاشت داینامیک جداول و فیلدها (JSON)
            $table->json('entity_mapping');
            $table->json('workflow_mapping');

            // تنظیمات کاربران مجازی
            $table->unsignedBigInteger('virtual_user_id'); // UserID پرسنل بدون اتوماسیون
            $table->unsignedBigInteger('virtual_role_id'); // RoleID دبیرخانه مجازی
            $table->unsignedBigInteger('target_role_id')->nullable(); // RoleID گیرنده پیش‌فرض
            $table->unsignedBigInteger('target_action_code')->default(13); // ActionCode پیش‌فرض (جهت اقدام)

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vs_templates');
    }
};
