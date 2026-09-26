<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_post_mappings', function (Blueprint $table) {
            $table->id();

            // نوع نگاشت: 'title_pattern' یا 'unit'
            $table->string('mapping_type', 20);

            // الگوی عنوان پست (برای mapping_type = 'title_pattern')
            // مثال: "%دامپتراک%" یا "راننده ماشین آلات سنگین"
            $table->string('post_title_pattern', 255)->nullable();

            // واحد سازمانی (برای mapping_type = 'unit')
            $table->unsignedBigInteger('organizational_unit_id')->nullable();

            // شناسنامه مقصد
            $table->unsignedBigInteger('assessment_post_id');

            // توضیحات
            $table->text('description')->nullable();

            // فعال/غیرفعال
            $table->boolean('is_active')->default(true);

            // سازنده
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // ایندکس‌ها
            $table->index('mapping_type');
            $table->index('assessment_post_id');
            $table->index('organizational_unit_id');

            // Foreign keys
            $table->foreign('assessment_post_id')
                ->references('id')
                ->on('assessment_posts')
                ->onDelete('cascade');

            $table->foreign('organizational_unit_id')
                ->references('id')
                ->on('organizational_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_post_mappings');
    }
};
