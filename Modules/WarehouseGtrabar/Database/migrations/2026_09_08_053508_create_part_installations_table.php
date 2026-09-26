<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('part_code', 64);
            $table->string('part_name', 256);
            $table->bigInteger('part_sql_server_id')->nullable()->comment('PartID از SQL Server');
            $table->dateTime('installed_at');
            $table->dateTime('removed_at')->nullable();
            $table->string('installation_location', 256)->nullable()->comment('محل نصب روی تجهیز');
            $table->string('serial_number', 128)->nullable()->comment('شماره سریال قطعه');
            $table->text('notes')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'removed_at']);
            $table->index(['part_code', 'removed_at']);
            $table->index(['installed_at', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_installations');
    }
};
