<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تغییر نوع ستون type از ENUM به VARCHAR برای انعطاف‌پذیری بیشتر
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // بازگشت به حالت قبلی (در صورت نیاز)
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('type', ['system', 'info', 'warning', 'success'])->nullable()->change();
        });
    }
};
