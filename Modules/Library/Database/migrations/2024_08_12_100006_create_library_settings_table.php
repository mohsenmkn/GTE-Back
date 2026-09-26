<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        // مقادیر پیش‌فرض
        DB::table('library_settings')->insert([
            ['key' => 'max_loan_days', 'value' => '14', 'description' => 'حداکثر روز امانت'],
            ['key' => 'reminder_days_before', 'value' => '3', 'description' => 'یادآوری چند روز قبل از سررسید'],
            ['key' => 'reservation_expiry_hours', 'value' => '48', 'description' => 'انقضای رزرو تایید نشده (ساعت)'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('library_settings');
    }
};
