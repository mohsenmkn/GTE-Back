<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title', 256);
            $table->string('title_en', 256)->nullable();
            $table->tinyInteger('type')->nullable()->comment('1: کامیون, 2: لودر, 3: بیل مکانیکی, 4: سایر');
            $table->text('description')->nullable();
            $table->tinyInteger('state')->default(1)->comment('1: فعال, 2: غیرفعال, 3: در تعمیر');
            $table->foreignId('parent_equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'state']);
            $table->index(['type', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
