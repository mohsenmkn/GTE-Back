<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vs_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('vs_templates')->cascadeOnDelete();

            $table->string('title'); // موضوع درخواست
            $table->json('request_data'); // داده‌های فرم (متن نامه، گیرنده و...)

            // وضعیت در ERP
            $table->enum('status', [
                'pending',      // در انتظار
                'processing',   // در حال پردازش
                'sent',         // ارسال شده به اتوماسیون
                'completed',    // تکمیل شده
                'rejected',     // رد شده
                'failed'        // خطا در ارسال
            ])->default('pending');

            // ردیابی در اتوماسیون
            $table->unsignedBigInteger('automation_entity_code')->nullable(); // EntityCode
            $table->unsignedBigInteger('automation_send_code')->nullable(); // SendCode
            $table->string('automation_letter_number')->nullable(); // EntityNumber (شماره نامه)

            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('automation_entity_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vs_requests');
    }
};
