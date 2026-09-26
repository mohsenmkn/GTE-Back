<?php
// database/migrations/2024_01_01_000002_create_materials_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('unit')->nullable(); // واحد: عدد، کیلوگرم، متر، ...
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('current_stock', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0); // حداقل موجودی
            $table->decimal('max_stock', 15, 3)->default(0); // حداکثر موجودی
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'category_id', 'status']);
            $table->index(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
