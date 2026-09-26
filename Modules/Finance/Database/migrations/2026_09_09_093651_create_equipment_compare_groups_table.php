<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // گروه مقایسه
        Schema::create('equipment_compare_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('name');
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamps();
        });

        // اعضای گروه (تجهیزات انتخاب شده)
        Schema::create('equipment_compare_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compare_group_id')
                ->constrained('equipment_compare_groups')
                ->onDelete('cascade');
            $table->integer('dl_type_ref');
            $table->string('equipment_code', 64);
            $table->string('equipment_title')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['compare_group_id', 'equipment_code'], 'unique_compare_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_compare_items');
        Schema::dropIfExists('equipment_compare_groups');
    }
};
