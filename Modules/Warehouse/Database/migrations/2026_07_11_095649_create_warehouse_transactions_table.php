<?php
// database/migrations/2024_01_01_000003_create_warehouse_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wbs_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', ['purchase', 'sale', 'transfer', 'adjustment', 'return', 'damage']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 20, 2)->default(0);
            $table->date('transaction_date');
            $table->string('reference_number')->nullable(); // شماره فاکتور یا مرجع
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_id', 'type', 'transaction_date']);
            $table->index(['project_id', 'wbs_item_id']);
            $table->index(['contract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transactions');
    }
};
