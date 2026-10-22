<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('id')
                ->constrained('assessment_question_templates')
                ->nullOnDelete()
                ->comment('تمپلیت استفاده شده برای ساخت این شناسنامه');

            $table->unsignedInteger('template_version_used')
                ->nullable()
                ->after('template_id')
                ->comment('نسخه تمپلیت در لحظه import');

            $table->timestamp('last_template_sync_at')
                ->nullable()
                ->after('template_version_used')
                ->comment('آخرین زمان sync با تمپلیت');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn([
                'template_id',
                'template_version_used',
                'last_template_sync_at',
            ]);
        });
    }
};
