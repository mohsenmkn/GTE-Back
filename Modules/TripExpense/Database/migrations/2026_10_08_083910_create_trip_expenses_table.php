// Modules/TripExpense/Database/Migrations/2026_10_08_000006_create_trip_expenses_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->comment('مبلغ هزینه');
            $table->string('receipt_path')->comment('مسیر تصویر رسید');
            $table->date('expense_date')->comment('تاریخ هزینه');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->comment('وضعیت تایید');
            $table->boolean('exceeded_limit')->default(false)
                ->comment('آیا از سقف تجاوز کرده؟');
            $table->text('warning_message')->nullable()
                ->comment('پیام هشدار در صورت تجاوز');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'category_id']);
            $table->index(['status', 'exceeded_limit']);
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_expenses');
    }
};
