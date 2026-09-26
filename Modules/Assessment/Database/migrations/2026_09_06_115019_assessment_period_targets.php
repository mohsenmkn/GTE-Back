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
        Schema::create('assessment_period_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('assessment_periods')->cascadeOnDelete();

            // نوع هدف: گروه، خانواده شغلی، واحد سازمانی
            $table->enum('target_type', ['group', 'family', 'unit']);

            // شناسه هدف (group_id یا نام خانواده یا unit_id)
            $table->string('target_value');

            // ارزیاب: خودکار (مدیر مستقیم) یا شخص خاص
            $table->enum('evaluator_mode', ['auto_manager', 'specific']);
            $table->foreignId('evaluator_user_id')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
