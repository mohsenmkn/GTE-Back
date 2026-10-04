<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_temporary_exits', function (Blueprint $table) {
            $table->id();

            // ارتباط با allocation
            $table->unsignedBigInteger('allocation_id');
            $table->foreign('allocation_id')
                ->references('id')
                ->on('pre_warehouse_allocations')
                ->cascadeOnDelete();

            // ارتباط با purchase (برای دسترسی سریع)
            $table->unsignedBigInteger('purchase_id');
            $table->foreign('purchase_id')
                ->references('id')
                ->on('pre_warehouse_purchases')
                ->cascadeOnDelete();

            // اطلاعات خروج
            $table->integer('quantity')->comment('تعداد خروجی');
            $table->string('target_type', 50)->default('equipment')->comment('نوع هدف: equipment, vehicle, project, other');
            $table->string('target_code', 100)->comment('کد تجهیز/وسیله/پروژه');
            $table->string('target_description', 500)->comment('توضیحات محل مصرف');
            $table->string('site_name', 255)->nullable()->comment('نام سایت/محل مصرف');

            // اطلاعات ثبت
            $table->unsignedBigInteger('registered_by');
            $table->foreign('registered_by')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('exit_date')->useCurrent();

            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها
            $table->index('allocation_id');
            $table->index('purchase_id');
            $table->index('target_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_temporary_exits');
    }
};
