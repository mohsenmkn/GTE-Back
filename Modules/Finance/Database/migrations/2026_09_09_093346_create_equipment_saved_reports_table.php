<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('name');                          // نام گزارش ذخیره شده
            $table->integer('dl_type_ref');                  // نوع تجهیز (49=لودر, 77=دامپتراک, ...)
            $table->string('equipment_code', 64);            // کد تجهیز
            $table->string('equipment_title')->nullable();   // عنوان تجهیز (برای نمایش)
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_default')->default(false);   // گزارش پیش‌فرض کاربر
            $table->timestamps();

            $table->index(['user_id', 'dl_type_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_saved_reports');
    }
};
