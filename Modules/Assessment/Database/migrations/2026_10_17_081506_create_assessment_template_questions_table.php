<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_template_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')
                ->constrained('assessment_question_templates')
                ->cascadeOnDelete()
                ->comment('تمپلیت مادر');

            $table->foreignId('category_id')
                ->constrained('assessment_categories')
                ->cascadeOnDelete()
                ->comment('دسته‌بندی (منظر) سوال');

            $table->string('title')->comment('متن سوال');
            $table->unsignedTinyInteger('risk_level')
                ->default(3)
                ->comment('سطح ریسک (1-5)');
            $table->unsignedTinyInteger('required_score')
                ->default(3)
                ->comment('نمره مطلوب (1-5)');

            // مقادیر مشابه AssessmentQuestion
            $table->string('fix_deadline')
                ->default('mid_term')
                ->comment('immediate, short_term, mid_term, long_term');

            $table->unsignedInteger('sort_order')->default(0)->comment('ترتیب نمایش');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'is_active'], 'template_active_index');
            $table->index(['category_id'], 'category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_template_questions');
    }
};
