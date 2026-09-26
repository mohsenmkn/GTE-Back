<?php
// Modules/PettyCash/Database/migrations/2024_01_01_000002_create_petty_cash_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wbs_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['deposit', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['petty_cash_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
