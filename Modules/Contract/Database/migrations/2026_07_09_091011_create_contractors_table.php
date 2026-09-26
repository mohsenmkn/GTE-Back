// database/migrations/2024_01_01_000000_create_contractors_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // اطلاعات پایه
            $table->string('name');
            $table->string('code')->unique()->nullable(); // کد پیمانکار
            $table->string('registration_number')->nullable(); // شماره ثبت
            $table->string('economic_code')->nullable(); // کد اقتصادی
            $table->string('national_id')->nullable(); // شناسه ملی

            // اطلاعات تماس
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // آدرس
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();

            // اطلاعات مالی
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_card_number')->nullable();
            $table->string('shaba_number')->nullable(); // شماره شبا

            // اطلاعات تخصصی
            $table->enum('type', ['legal', 'real'])->default('legal'); // حقوقی/حقیقی
            $table->json('expertise')->nullable(); // زمینه تخصصی
            $table->json('certificates')->nullable(); // گواهینامه‌ها
            $table->text('description')->nullable();

            // وضعیت
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['code', 'national_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
