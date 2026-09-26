<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->string('source', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->string('source', 20)->change();
        });
    }
};
