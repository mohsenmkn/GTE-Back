// Modules/TripExpense/Database/Migrations/2026_10_08_000001_create_trucks_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('کد کامیون (مثلاً 120)');
            $table->string('plate_number')->comment('پلاک کامیون');
            $table->string('brand')->nullable()->comment('برند کامیون');
            $table->string('model')->nullable()->comment('مدل کامیون');
            $table->enum('status', ['active', 'inactive', 'maintenance'])
                ->default('active')
                ->comment('وضعیت کامیون');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
