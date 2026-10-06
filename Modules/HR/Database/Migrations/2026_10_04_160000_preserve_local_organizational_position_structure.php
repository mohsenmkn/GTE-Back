<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_positions', function (Blueprint $table) {
            $table->foreignId('source_organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->boolean('is_structure_overridden')->default(false);
        });
        DB::table('organizational_positions')->whereNotNull('gt_post_ref')
            ->update(['source_organizational_unit_id' => DB::raw('organizational_unit_id')]);
    }

    public function down(): void
    {
        Schema::table('organizational_positions', function (Blueprint $table) {
            $table->dropForeign(['source_organizational_unit_id']);
            $table->dropColumn(['source_organizational_unit_id', 'is_structure_overridden']);
        });
    }
};
