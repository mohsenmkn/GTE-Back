<?php
// Modules/SystemSettings/Database/Migrations/2026_09_05_000000_create_system_database_connections_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('نام اتصال مثل gtarabar');
            $table->string('title')->nullable()->comment('عنوان فارسی برای نمایش');
            $table->string('driver', 20)->default('sqlsrv');
            $table->string('host');
            $table->string('port', 10)->default('1433');
            $table->string('database');
            $table->string('username');
            $table->text('password'); // با encrypted cast ذخیره می‌شود
            $table->string('charset', 20)->default('utf8');
            $table->string('collation', 50)->default('Persian_100_CI_AI_SC');
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_database_connections');
    }
};
