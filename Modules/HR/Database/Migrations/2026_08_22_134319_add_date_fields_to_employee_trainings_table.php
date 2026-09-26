<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->smallInteger('start_year')->nullable();
            $table->tinyInteger('start_month')->nullable();
            $table->smallInteger('end_year')->nullable();
            $table->tinyInteger('end_month')->nullable();
            $table->string('date_precision', 10)->nullable(); // year | month
        });
    }

    public function down(): void
    {
        Schema::table('employee_trainings', function (Blueprint $table) {
            $table->dropColumn(['start_year', 'start_month', 'end_year', 'end_month', 'date_precision']);
        });
    }
};
