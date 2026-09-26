<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_code', 10)
                ->nullable()
                ->unique()
                ->after('email');

            $table->string('personnel_code', 20)
                ->nullable()
                ->unique()
                ->after('national_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['national_code']);
            $table->dropUnique(['personnel_code']);
            $table->dropColumn(['national_code', 'personnel_code']);
        });
    }
};
