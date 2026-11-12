<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 80)->index();
            $table->string('fingerprint', 64)->index();
            $table->string('severity', 20)->default('medium')->index();
            $table->string('title', 160);
            $table->text('description');
            $table->string('source_ip', 45)->nullable()->index();
            $table->string('subject', 100)->nullable()->index();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'last_detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
