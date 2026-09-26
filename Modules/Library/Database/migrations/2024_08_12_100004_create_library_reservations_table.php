<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_copy_id')->constrained('library_book_copies')->cascadeOnDelete();

            // تاریخ‌های رزرو
            $table->timestamp('reservation_date'); // تاریخ ثبت رزرو
            $table->date('expected_pickup_date'); // تاریخ تحویل مقرر (انتخاب کاربر)
            $table->timestamp('actual_pickup_date')->nullable(); // تاریخ تحویل واقعی (توسط مدیر)

            // تاریخ‌های بازگشت
            $table->date('expected_return_date'); // تاریخ بازگشت مقرر
            $table->date('actual_return_date')->nullable(); // تاریخ بازگشت واقعی

            // وضعیت
            $table->enum('status', [
                'pending',      // در انتظار تایید
                'approved',     // تایید شده
                'picked_up',    // تحویل داده شده
                'returned',     // بازگشت داده شده
                'cancelled',    // لغو شده
                'expired'       // منقضی شده
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();

            // ایندکس‌ها برای سرعت
            $table->index(['user_id', 'status']);
            $table->index(['book_copy_id', 'status']);
            $table->index('expected_return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reservations');
    }
};
