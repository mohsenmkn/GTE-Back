<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {

            $table->foreignId('organizational_position_id')
                ->nullable()
                ->after('organizational_unit_id')
                ->constrained('organizational_positions')
                ->nullOnDelete();

            $table->unsignedBigInteger('gt_post_ref')
                ->nullable()
                ->after('organizational_position_id');

            $table->unsignedBigInteger('gt_job_ref')
                ->nullable()
                ->after('gt_post_ref');

            $table->index('gt_post_ref');
            $table->index('gt_job_ref');
        });
    }

    public function down(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->dropForeign(['organizational_position_id']);
            $table->dropColumn([
                'organizational_position_id',
                'gt_post_ref',
                'gt_job_ref',
            ]);
        });
    }
};
