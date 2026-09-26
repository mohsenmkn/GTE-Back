<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── assessment_posts: ستون‌های grade/unit/domain ──
        Schema::table('assessment_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_posts', 'grade')) {
                $table->string('grade', 50)->nullable()->after('title');
            }
            if (!Schema::hasColumn('assessment_posts', 'unit')) {
                $table->string('unit', 150)->nullable()->after('grade');
            }
            if (!Schema::hasColumn('assessment_posts', 'domain')) {
                $table->string('domain', 150)->nullable()->after('unit');
            }
        });

        // ── assessment_questions: ستون required_score ──
        Schema::table('assessment_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_questions', 'required_score')) {
                $table->unsignedTinyInteger('required_score')->nullable()->after('title');
            }
        });

        // ── nullable کردن بدون ->change() (دور زدن باگ DBAL) ──
        DB::statement('ALTER TABLE assessment_questions MODIFY COLUMN risk_level TINYINT UNSIGNED NULL');
        DB::statement('ALTER TABLE assessment_questions MODIFY COLUMN fix_deadline VARCHAR(20) NULL');
    }

    public function down(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->dropColumn(['grade', 'unit', 'domain']);
        });

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropColumn(['required_score']);
        });

        DB::statement('ALTER TABLE assessment_questions MODIFY COLUMN risk_level TINYINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE assessment_questions MODIFY COLUMN fix_deadline VARCHAR(20) NOT NULL');
    }
};
