<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->constrained('pre_warehouse_purchases')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            // مقادیر
            $table->unsignedInteger('allocated_qty');
            $table->unsignedInteger('received_qty')->default(0);

            // وضعیت
            $table->enum('status', [
                'pending',
                'partially_received',
                'fully_received',
                'rejected',
            ])->default('pending');

            // اطلاعات دریافت
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('receive_notes')->nullable();

            // اطلاعات رد
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_id');
            $table->index('warehouse_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_allocations');
    }
};
