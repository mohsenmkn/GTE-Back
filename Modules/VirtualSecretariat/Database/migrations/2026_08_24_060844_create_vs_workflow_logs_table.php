<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vs_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('vs_requests')->cascadeOnDelete();

            $table->unsignedBigInteger('automation_receiver_code')->nullable(); // Code در ActiveSend_Receivers
            $table->unsignedBigInteger('receiver_role_id')->nullable();
            $table->unsignedBigInteger('action_code')->nullable();
            $table->string('action_name')->nullable(); // از جدول Actions

            $table->enum('state', [
                'waiting',        // در انتظار
                'in_progress',    // در حال بررسی
                'finished',       // پایان یافته
                'rejected'        // رد شده
            ])->default('waiting');

            $table->timestamp('receive_date')->nullable();
            $table->timestamp('response_date')->nullable();
            $table->string('response_text')->nullable();

            $table->timestamps();

            $table->index(['request_id', 'state']);
            $table->index('automation_receiver_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vs_workflow_logs');
    }
};
