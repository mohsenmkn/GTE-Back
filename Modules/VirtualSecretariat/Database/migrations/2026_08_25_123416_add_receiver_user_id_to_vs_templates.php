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
            $table->unsignedBigInteger('receiver_user_id')
                ->nullable()
                ->after('receiver_role_id')
                ->comment('UserID گیرنده نامه در اتوماسیون');
        });
    }

    public function down(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            $table->dropColumn('receiver_user_id');
        });
    }
};
