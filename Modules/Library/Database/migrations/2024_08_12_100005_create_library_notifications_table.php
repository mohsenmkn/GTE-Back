<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('library_reservations')->cascadeOnDelete();

            $table->enum('type', [
                'reminder',      // یادآوری سررسید
                'overdue',       // اخطار تاخیر
                'approval',      // تایید رزرو
                'ready',         // آماده تحویل
                'pending',       // 🔑 اضافه شد: در انتظار تایید
                'cancelled',     // 🔑 اضافه شد: لغو شده
            ])->default('pending');

            $table->text('message');
            $table->string('mobile', 20)->nullable(); // شماره موبایل مقصد
            $table->timestamp('sent_at')->nullable(); // زمان ارسال پیامک
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_notifications');
    }
};
