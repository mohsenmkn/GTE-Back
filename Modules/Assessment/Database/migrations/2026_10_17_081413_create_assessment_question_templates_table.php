<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_question_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('عنوان تمپلیت (مثلاً: تمپلیت عمومی سرپرستان)');
            $table->string('grade')->comment('خانواده شغلی (مثلاً: سرپرست/کارشناس ارشد)');
            $table->unsignedInteger('version')->default(1)->comment('نسخه تمپلیت');
            $table->text('description')->nullable()->comment('توضیحات تمپلیت');
            $table->boolean('is_active')->default(true)->comment('وضعیت فعال بودن');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('ایجاد کننده (معمولاً HR Admin)');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('تایید کننده نهایی');

            $table->timestamp('approved_at')->nullable()->comment('زمان تایید');
            $table->timestamps();
            $table->softDeletes();

            // یک تمپلیت نمی‌تونه title و version یکسان با تمپلیت دیگه داشته باشه
            $table->unique(['title', 'version'], 'template_version_unique');

            // ایندکس برای جستجوی سریع بر اساس grade
            $table->index(['grade', 'is_active'], 'grade_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_question_templates');
    }
};
