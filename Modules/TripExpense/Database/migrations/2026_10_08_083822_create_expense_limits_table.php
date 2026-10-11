// Modules/TripExpense/Database/Migrations/2026_10_08_000004_create_expense_limits_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->enum('limit_type', ['per_trip', 'monthly'])
                ->comment('per_trip = سقف هر سفر، monthly = سقف ماهانه');
            $table->decimal('amount', 15, 2)->comment('مبلغ سقف');
            $table->date('effective_from')->comment('تاریخ شروع اعتبار');
            $table->date('effective_to')->nullable()->comment('تاریخ پایان (null = نامحدود)');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'limit_type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_limits');
    }
};
