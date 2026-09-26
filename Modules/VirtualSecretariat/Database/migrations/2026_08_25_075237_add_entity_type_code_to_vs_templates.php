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
            $table->unsignedBigInteger('entity_type_code')
                ->nullable()
                ->after('letter_number_format')
                ->comment('کد نوع نامه در اتوماسیون (EntityTypeCode)');
        });
    }

    public function down(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            $table->dropColumn('entity_type_code');
        });
    }
};
