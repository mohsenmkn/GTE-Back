<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('allocation_id')
                ->constrained('pre_warehouse_allocations')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')
                ->nullable()
                ->constrained('warehouse_locations')
                ->nullOnDelete();

            $table->string('location_name', 255);
            $table->text('description')->nullable();
            $table->string('section_code', 50)->nullable();
            $table->unsignedInteger('assigned_qty');

            $table->timestamps();
            $table->softDeletes();

            $table->index('allocation_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_locations');
    }
};
