<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_statute_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // شناسه‌های گستراب
            $table->unsignedBigInteger('gt_employee_id')->index();
            $table->unsignedBigInteger('gt_statute_id')->unique();

            // سمت و شغل در این حکم
            $table->unsignedBigInteger('post_ref')->nullable();
            $table->string('post_code', 50)->nullable();
            $table->string('post_title', 255)->nullable();
            $table->unsignedBigInteger('job_ref')->nullable();
            $table->string('job_code', 50)->nullable();
            $table->string('job_title', 255)->nullable();

            // واحد سازمانی در این حکم
            $table->unsignedBigInteger('department_ref')->nullable();
            $table->unsignedBigInteger('organizational_structure_ref')->nullable();

            // اطلاعات حکم
            $table->dateTime('issue_date')->nullable();
            $table->dateTime('apply_date')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->string('statute_number', 100)->nullable();

            // آیا حکم فعلی است؟
            $table->boolean('is_current')->default(false);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statute_history');
    }
};
