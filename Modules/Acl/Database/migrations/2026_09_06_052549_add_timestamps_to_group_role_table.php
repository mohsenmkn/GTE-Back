<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_role', function (Blueprint $table) {
            $table->timestamps(); // اضافه کردن created_at و updated_at
        });
    }

    public function down(): void
    {
        Schema::table('group_role', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
