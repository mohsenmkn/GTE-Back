<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_warehouse_custodian_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role_title', 100)->default('متولی کالا'); // عنوان نقش متولی
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // هر واحد فقط یک متولی فعال داشته باشد
            //$table->unique(['organizational_unit_id', 'user_id']);
            $table->unique(
                ['organizational_unit_id', 'user_id'],
                'pre_wh_custodian_org_user_unique'
            );
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_warehouse_custodian_mappings');
    }
};
