<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->string('source', 20)->default('excel')->after('domain'); // excel | auto
        });
    }

    public function down(): void
    {
        Schema::table('assessment_posts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
