<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('national_code', 20)->index();

            // شناسه یکتا: ترکیب national_code + course_code
            $table->string('external_id', 100)->unique();

            // اطلاعات کاربر (snapshot)
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('deputy', 200)->nullable();       // معاونت
            $table->string('management', 200)->nullable();   // مدیریت
            $table->string('post_title', 255)->nullable();   // پست سازمانی

            // اطلاعات دوره
            $table->string('course_code', 50)->index();
            $table->string('course_title', 500);
            $table->string('session_duration', 20)->nullable(); // HH:MM:SS
            $table->decimal('performance_hours', 8, 2)->default(0);

            // داده خام برای debug
            $table->json('soap_raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'course_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_trainings');
    }
};
