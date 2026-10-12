<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->foreignId('parent_position_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('organizational_positions')
                ->nullOnDelete();

            // اضافه کردن index برای performance
            $table->index('parent_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizational_units', function (Blueprint $table) {
            $table->dropForeign(['parent_position_id']);
            $table->dropIndex(['parent_position_id']);
            $table->dropColumn('parent_position_id');
        });
    }
};
