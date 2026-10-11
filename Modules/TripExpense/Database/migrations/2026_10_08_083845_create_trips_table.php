// Modules/TripExpense/Database/Migrations/2026_10_08_000005_create_trips_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
            $table->string('origin')->comment('مبدأ');
            $table->string('destination')->comment('مقصد');
            $table->date('start_date')->comment('تاریخ شروع سفر');
            $table->date('end_date')->nullable()->comment('تاریخ پایان سفر');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                ->default('pending')
                ->comment('وضعیت سفر');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['truck_id', 'start_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
