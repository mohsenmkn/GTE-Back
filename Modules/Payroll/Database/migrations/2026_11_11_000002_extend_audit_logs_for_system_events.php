<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_audit_logs', function (Blueprint $table) {
            $table->string('module', 50)->default('Payroll')->after('user_id')->index();
            $table->string('status', 20)->default('success')->after('action')->index();
            $table->string('subject', 100)->nullable()->after('target_employee_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropIndex(['status']);
            $table->dropIndex(['subject']);
            $table->dropColumn(['module', 'status', 'subject']);
        });
    }
};
