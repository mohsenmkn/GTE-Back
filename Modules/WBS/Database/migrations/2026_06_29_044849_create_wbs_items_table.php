<?php
// Modules/WBS/Database/migrations/2024_01_01_000001_create_wbs_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbs_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wbs_items')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('category', ['civil', 'electrical', 'mechanical', 'process']);
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 20, 2)->default(0);
            $table->integer('weight')->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'on_hold'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'category']);
            $table->index(['project_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wbs_items');
    }
};
