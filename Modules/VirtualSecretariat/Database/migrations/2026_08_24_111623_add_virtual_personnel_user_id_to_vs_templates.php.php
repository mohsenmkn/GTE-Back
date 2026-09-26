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
            // ✅ اضافه کردن فیلد CreatorID (UserID کاربر مجازی)
            $table->unsignedBigInteger('virtual_personnel_user_id')
                ->after('virtual_personnel_role_id')
                ->nullable()
                ->comment('UserID کاربر مجازی (CreatorID در Entity_Dakhli)');
        });
    }

    public function down(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            $table->dropColumn('virtual_personnel_user_id');
        });
    }
};
