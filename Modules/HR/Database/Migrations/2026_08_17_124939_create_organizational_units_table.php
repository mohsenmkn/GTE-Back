<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();

            // شناسه دپارتمان در گستراب
            $table->unsignedBigInteger('gt_department_id')->nullable()->unique();

            $table->string('code', 50)->nullable()->index();
            $table->string('title', 255);

            // ✅ سلسله مراتب
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('organizational_units')
                ->nullOnDelete();

            // ✅ Materialized Path برای کوئری سریع زیرمجموعه‌ها
            // مثال: /1/5/12/
            $table->string('path', 500)->nullable()->index();
            $table->unsignedTinyInteger('level')->default(1);

            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
