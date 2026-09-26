<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('personnel_code', 50)->index();

            // شناسه‌های گستراب
            $table->unsignedBigInteger('gt_employee_id')->nullable()->index();
            $table->unsignedBigInteger('gt_statute_id')->nullable();

            // سمت و شغل
            $table->string('post_code', 50)->nullable();
            $table->string('post_title', 255)->nullable();
            $table->string('job_code', 50)->nullable();
            $table->string('job_title', 255)->nullable();

            // ✅ واحد سازمانی
            $table->foreignId('organizational_unit_id')
                ->nullable()
                ->constrained('organizational_units')
                ->nullOnDelete();

            $table->unsignedBigInteger('gt_organizational_structure_ref')
                ->nullable();

            // وضعیت sync
            $table->timestamp('synced_at')->nullable();
            $table->boolean('sync_failed')->default(false);

            $table->timestamps();

            $table->unique('user_id');
            $table->index('organizational_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_positions');
    }
};
