<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_type')) {
                $table->string('employee_type', 20)
                    ->default('personnel')
                    ->after('personnel_code')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_type')) {
                $table->dropIndex(['employee_type']);
                $table->dropColumn('employee_type');
            }
        });
    }
};
