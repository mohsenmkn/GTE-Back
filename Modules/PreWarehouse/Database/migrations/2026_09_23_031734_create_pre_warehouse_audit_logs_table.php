<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->morphs('auditable');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('action', [
                'created',
                'updated',
                'allocated',
                'received',
                'approved',
                'rejected',
                'location_assigned',
                'finalized',
                'status_changed',
            ]);

            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('changes')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_audit_logs');
    }
};
