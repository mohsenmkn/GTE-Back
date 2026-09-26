<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ۱. مناظر شایستگی (دسته‌بندی سوالات) ──
        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── ۲. پست‌های دارای شناسنامه شایستگی ──
        Schema::create('assessment_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->unique();   // مثال: سرپرست عملیات ریلی
            $table->string('code', 50)->nullable();
            // ✅ فیلدهای توسعه آینده (الان nullable)
            $table->string('min_education', 50)->nullable();      // حداقل تحصیلات
            $table->unsignedTinyInteger('min_experience_years')->nullable(); // حداقل تجربه
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── ۳. سوالات شایستگی ──
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')
                ->constrained('assessment_posts')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('assessment_categories')
                ->cascadeOnDelete();

            $table->text('title');

            $table->unsignedTinyInteger('risk_level')
                ->nullable()
                ->comment('سطح ریسک از 1 تا 5');

            $table->string('fix_deadline', 20)
                ->nullable()
                ->comment('immediate/short_term/mid_term/long_term');

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index(['post_id', 'category_id']);
        });

        // ── ۴. چرخه‌های ارزیابی ──
        Schema::create('assessment_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);                      // مثال: ارزیابی سالانه 1405
            $table->string('type', 20)->default('annual');     // annual / transfer
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('draft');    // draft/active/closed
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ── . ارزیابی‌ها ─
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->nullable()->constrained('assessment_cycles')->nullOnDelete();
            $table->foreignId('employee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('assessment_posts')->cascadeOnDelete(); // پست هدف
            $table->string('status', 20)->default('draft');    // draft/submitted/approved/rejected
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_user_id', 'cycle_id']);
            $table->index('evaluator_user_id');
        });

        // ── . پاسخ‌ها (نمره ۱-۵) ──
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');               // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'question_id']);
        });

        // ── . گپ‌ها (ذخیره برای پیگیری) ──
        Schema::create('assessment_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('required_score');
            $table->unsignedTinyInteger('actual_score');
            $table->tinyInteger('gap');                         // مطلوب − واقعی
            $table->smallInteger('weighted_gap');               // گپ × ریسک
            $table->string('fix_deadline', 20);
            $table->string('status', 20)->default('open');      // open/actioned/closed/monitored
            $table->timestamps();
            $table->unique(['assessment_id', 'question_id']);
        });

        // ── . کاتالوگ روش‌های رفع خلا (~۴۰ مورد) ──
        Schema::create('assessment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── . اقدامات اصلاحی ─
        Schema::create('assessment_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gap_id')->constrained('assessment_gaps')->cascadeOnDelete();
            $table->foreignId('method_id')->nullable()->constrained('assessment_methods')->nullOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();               // از مهلت رفع محاسبه می‌شود
            $table->string('status', 20)->default('planned');   // planned/in_progress/completed/cancelled
            $table->timestamp('completed_at')->nullable();
            $table->string('training_course_code', 50)->nullable(); // ✅ لینک به ماژول آموزش
            $table->timestamps();
        });

        // ── ۱۰. کاتالوگ ریسک‌های عمومی کاستی (توسعه آینده) ──
        Schema::create('assessment_general_risks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_actions');
        Schema::dropIfExists('assessment_gaps');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_cycles');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessment_methods');
        Schema::dropIfExists('assessment_general_risks');
        Schema::dropIfExists('assessment_posts');
        Schema::dropIfExists('assessment_categories');
    }
};
