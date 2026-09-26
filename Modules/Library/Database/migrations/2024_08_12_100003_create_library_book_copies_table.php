<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('copy_code', 50)->unique(); // بارکد یکتا
            $table->enum('status', ['available', 'reserved', 'lent', 'maintenance'])->default('available');
            $table->text('condition_note')->nullable(); // توضیحات وضعیت فیزیکی
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_book_copies');
    }
};
