<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('author', 255);
            $table->string('translator', 255)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->string('isbn', 50)->nullable()->unique();
            $table->year('publish_year')->nullable();
            $table->foreignId('category_id')->constrained('library_categories')->cascadeOnDelete();
            $table->integer('pages')->nullable();
            $table->string('language', 50)->default('فارسی');
            $table->string('cover_image', 500)->nullable(); // مسیر تصویر
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_books');
    }
};
