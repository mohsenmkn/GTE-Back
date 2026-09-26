<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_evaluation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluator_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('evaluated_group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamps();

            // نام سفارشی کوتاه برای دور زدن محدودیت ۶۴ کاراکتری
            $table->unique(
                ['evaluator_group_id', 'evaluated_group_id'],
                'aer_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_evaluation_rules');
    }
};
