<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_type_accesses', function (Blueprint $table) {
            $table->id();

            // می‌تواند به user یا role اختصاص یابد
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('cascade');

            // نوع تجهیز از راهکاران (DLTypeRef)
            $table->integer('dl_type_ref');
            $table->string('dl_type_title')->nullable(); // برای نمایش

            // اکشن‌های مجاز
            $table->boolean('can_view')->default(true);
            $table->boolean('can_export')->default(false);

            $table->timestamps();

            // یک نوع تجهیز فقط یکبار برای هر user/role
            $table->unique(['user_id', 'dl_type_ref'], 'unique_user_type');
            $table->unique(['role_id', 'dl_type_ref'], 'unique_role_type');

            $table->index('dl_type_ref');
        });

        // ✅ اضافه کردن CHECK constraint به صورت raw SQL
        DB::statement('ALTER TABLE equipment_type_accesses
            ADD CONSTRAINT chk_user_or_role
            CHECK (user_id IS NOT NULL OR role_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_type_accesses');
    }
};
