<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_relatives', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // شناسه‌های گستراب
            $table->unsignedBigInteger('gt_employee_id')->index();
            $table->unsignedBigInteger('gt_relative_id')->unique();

            // اطلاعات فرد
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('father_name', 100)->nullable();
            $table->integer('relation_code')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('id_number', 50)->nullable();
            $table->date('birth_date')->nullable();

            // کدها (ترجمه در زمان نمایش انجام می‌شود)
            $table->integer('degree_code')->nullable();
            $table->integer('education_state_code')->nullable();
            $table->integer('physical_state_code')->nullable();
            $table->integer('marital_status_code')->nullable();
            $table->integer('relative_type')->nullable();

            // سایر
            $table->string('job', 200)->nullable();
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();

            // وضعیت sync
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'relation_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_relatives');
    }
};
