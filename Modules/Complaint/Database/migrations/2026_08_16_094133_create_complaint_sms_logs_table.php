<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_sms_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_id')
                ->nullable()
                ->constrained('complaints')
                ->nullOnDelete();

            $table->string('mobile', 20);
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->json('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_sms_logs');
    }
};
