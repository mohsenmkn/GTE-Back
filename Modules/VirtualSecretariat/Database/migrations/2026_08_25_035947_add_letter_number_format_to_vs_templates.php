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
            $table->json('letter_number_format')->nullable()->after('workflow_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('vs_templates', function (Blueprint $table) {
            $table->dropColumn('letter_number_format');
        });
    }
};
