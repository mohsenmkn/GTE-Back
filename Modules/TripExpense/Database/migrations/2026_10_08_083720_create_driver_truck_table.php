// Modules/TripExpense/Database/Migrations/2026_10_08_000002_create_driver_truck_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_truck', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
            $table->date('start_date')->comment('تاریخ شروع رانندگی');
            $table->date('end_date')->nullable()->comment('تاریخ پایان (null = فعال)');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['truck_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_truck');
    }
};
