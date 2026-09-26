<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->constrained('pre_warehouse_purchases')
                ->cascadeOnDelete();

            $table->enum('approver_type', ['custodian', 'warehouse_manager'])
                ->default('custodian');
            $table->foreignId('approver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index('purchase_id');
            $table->index('approver_id');
            $table->index('status');
            $table->unique(['purchase_id', 'approver_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_approvals');
    }
};
