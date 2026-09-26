<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_purchases', function (Blueprint $table) {
            $table->id();

            // اطلاعات خرید
            $table->foreignId('commercial_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained('pre_warehouse_items')
                ->cascadeOnDelete();
            $table->foreignId('target_unit_id')
                ->nullable()
                ->constrained('organizational_units')
                ->nullOnDelete();

            // مقادیر
            $table->unsignedInteger('quantity');
            $table->string('unit_of_measurement', 50)->default('عدد');
            $table->text('description')->nullable();

            // وضعیت workflow
            $table->enum('status', [
                'registered',              // 1. ثبت شده توسط بازرگانی
                'allocated',               // 2. تخصیص انبار انجام شده
                'partially_received',      // 3. دریافت جزئی
                'fully_received',          // 4. دریافت کامل توسط انبار
                'custodian_approved',      // 5. تأیید متولی
                'location_assigned',       // 6. محل نگهداری مشخص شده
                'finalized',               // 7. نهایی شده
                'rejected_by_warehouse',   // رد شده توسط انبار
                'rejected_by_custodian',   // رد شده توسط متولی
            ])->default('registered');

            // مقادیر تجمیعی
            $table->unsignedInteger('total_allocated_qty')->default(0);
            $table->unsignedInteger('total_received_qty')->default(0);

            // متادیتا
            $table->json('metadata')->nullable();
            // مثال: {"invoice_number": "1234", "purchase_date": "1405/07/01", "supplier": "شرکت X"}

            // تاریخ‌های مراحل
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('fully_received_at')->nullable();
            $table->timestamp('custodian_approved_at')->nullable();
            $table->timestamp('location_assigned_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('commercial_user_id');
            $table->index('item_id');
            $table->index('target_unit_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_purchases');
    }
};
