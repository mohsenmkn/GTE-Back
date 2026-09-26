<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_positions', function (Blueprint $table) {
            $table->id();

            // واحد سازمانی
            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->cascadeOnDelete();

            // سمت والد در همان واحد
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('organizational_positions')
                ->nullOnDelete();

            // Reference به راهکاران
            $table->unsignedBigInteger('gt_post_ref')
                ->nullable();

            $table->string('post_code', 50)->nullable();
            $table->string('post_title', 255)->nullable();

            $table->unsignedBigInteger('gt_job_ref')
                ->nullable();

            $table->string('job_code', 50)->nullable();
            $table->string('job_title', 255)->nullable();

            // آیا سمت از راهکاران آمده یا توسط ساختار داخلی ایجاد شده؟
            $table->boolean('is_custom')->default(false);

            // فعال / غیرفعال
            $table->boolean('is_active')->default(true);

            // ترتیب نمایش در چارت
            $table->unsignedInteger('sort_order')->default(0);

            $table->text('description')->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index('organizational_unit_id');
            $table->index('parent_id');
            $table->index('gt_post_ref');

            // یک سمت راهکاران در یک واحد فقط یک بار
            $table->unique(
                ['organizational_unit_id', 'gt_post_ref'],
                'org_position_unit_gt_post_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_positions');
    }
};
