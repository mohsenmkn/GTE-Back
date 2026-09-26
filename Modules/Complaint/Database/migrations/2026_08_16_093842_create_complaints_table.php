<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('complaint_category_id')
                ->constrained('complaint_categories')
                ->restrictOnDelete();

            $table->string('tracking_code')->unique();
            $table->string('subject');
            $table->text('description');

            $table->string('status')->default('pending')->index();
            $table->string('priority')->default('medium')->index();

            $table->string('jalali_date', 10)->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
