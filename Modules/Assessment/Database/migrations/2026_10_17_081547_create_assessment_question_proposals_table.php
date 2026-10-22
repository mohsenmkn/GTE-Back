<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_question_proposals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('target_post_id')
                ->constrained('assessment_posts')
                ->cascadeOnDelete()
                ->comment('شناسنامه‌ای که سوال براش طراحی شده');

            $table->foreignId('proposed_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('بالادستی که پیشنهاد داده');

            $table->foreignId('category_id')
                ->constrained('assessment_categories')
                ->cascadeOnDelete()
                ->comment('دسته‌بندی سوال');

            $table->string('title')->comment('متن سوال پیشنهادی');
            $table->unsignedTinyInteger('risk_level')->default(3);
            $table->unsignedTinyInteger('required_score')->default(3);
            $table->string('fix_deadline')->default('mid_term');

            $table->string('status')
                ->default('pending')
                ->comment('pending, approved, rejected');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('بررسی کننده (HR Admin)');

            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable()->comment('دلیل رد (در صورت رد)');

            $table->timestamps();

            $table->index(['target_post_id', 'status'], 'post_status_index');
            $table->index(['proposed_by_user_id'], 'proposer_index');
            $table->index(['status'], 'status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_question_proposals');
    }
};
