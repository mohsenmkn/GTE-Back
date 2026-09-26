<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vs_requests', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('error_message')
                ->comment('یادداشت ادمین');
            $table->unsignedBigInteger('admin_user_id')->nullable()->after('admin_note')
                ->comment('UserID ادمینی که تغییر وضعیت داده');
        });
    }

    public function down(): void
    {
        Schema::table('vs_requests', function (Blueprint $table) {
            $table->dropColumn(['admin_note', 'admin_user_id']);
        });
    }
};
