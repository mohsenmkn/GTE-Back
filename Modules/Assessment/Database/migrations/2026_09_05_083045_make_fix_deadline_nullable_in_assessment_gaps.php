<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ بدون DBAL — مستقیم ALTER خام
        DB::statement('ALTER TABLE assessment_gaps MODIFY COLUMN fix_deadline VARCHAR(20) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assessment_gaps MODIFY COLUMN fix_deadline VARCHAR(20) NOT NULL DEFAULT 'mid_term'");
    }
};
