<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            // تغییر نام ستون‌ها
            $table->renameColumn('virtual_user_id', 'virtual_personnel_role_id');
            $table->renameColumn('virtual_role_id', 'receiver_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            // بازگرداندن به نام قبلی
            $table->renameColumn('virtual_personnel_role_id', 'virtual_user_id');
            $table->renameColumn('receiver_role_id', 'virtual_role_id');
        });
    }
};
