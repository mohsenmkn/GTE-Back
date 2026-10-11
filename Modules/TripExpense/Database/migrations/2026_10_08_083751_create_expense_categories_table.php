// Modules/TripExpense/Database/Migrations/2026_10_08_000003_create_expense_categories_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('نام دسته (مثلاً آب معدنی، ناهار)');
            $table->string('code')->unique()->comment('کد یکتا');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0)->comment('ترتیب نمایش');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
