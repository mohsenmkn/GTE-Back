<?php

namespace Modules\VirtualSecretariat\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vs_letter_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('vs_templates')->cascadeOnDelete();
            $table->string('prefix', 10); // مثلاً EMP
            $table->string('year', 10); // مثلاً 1405
            $table->unsignedBigInteger('current_number')->default(0); // آخرین شماره استفاده شده
            $table->timestamps();

            $table->unique(['template_id', 'prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vs_letter_counters');
    }
};
