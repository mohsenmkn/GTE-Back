<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('gt_department_ref')
                ->nullable()
                ->after('gt_organizational_structure_ref');

            $table->index('gt_department_ref');
        });
    }

    public function down(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->dropColumn('gt_department_ref');
        });
    }
};
